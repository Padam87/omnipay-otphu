<?php

use Clapp\OtpHu\Gateway as OtpHuGateway;
use Clapp\OtpHu\TransactionIdFactory;
use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;
use Illuminate\Validaton\ValidationException;
use Omnipay\Common\Exception\InvalidRequestException;

class GatewayTest extends TestCase{
    public function testGatewayCreation(){
        $gateway = Omnipay::create("\\".OtpHuGateway::class);

        $this->assertInstanceOf(OtpHuGateway::class, $gateway);
    }

    public function testTestMode(){
        $gateway = Omnipay::create("\\".OtpHuGateway::class);

        $shopId = $this->faker->randomNumber;

        $gateway->setShopId($shopId);
        $this->assertEquals($gateway->getShopId($shopId), $shopId);

        $gateway->setTestMode(true);
        $this->assertEquals($gateway->getShopId($shopId), "#".$shopId);

        $gateway->setTestMode(false);
        $this->assertEquals($gateway->getShopId($shopId), $shopId);
    }

    public function testMissingTransactionIdFactory(){
        $gateway = Omnipay::create("\\".OtpHuGateway::class);
        try {
            $gateway->purchase([]);
        }catch(InvalidArgumentException $e){
            $this->setLastException($e);
        }
        $this->assertLastException(InvalidArgumentException::class);
    }

    public function testTransactionIdWithoutFactory(){
        $gateway = Omnipay::create("\\".OtpHuGateway::class);

        try{
            $gateway->purchase([
                'transactionId' => $this->faker->creditCardNumber,
            ]);
        }catch(InvalidRequestException $e){
            $this->setLastException($e);
        }
        $this->assertLastException(InvalidRequestException::class);
    }

    public function testTransactionIdWithoutFactoryOnGateway(){
        $gateway = Omnipay::create("\\".OtpHuGateway::class);

        $gateway->setTransactionId($this->faker->creditCardNumber);

        try{
            $gateway->purchase([]);
        }catch(InvalidRequestException $e){
            $this->setLastException($e);
        }
        $this->assertLastException(InvalidRequestException::class);
    }



    public function testTransactionIdWitFactory(){
        $gateway = Omnipay::create("\\".OtpHuGateway::class);

        $mock = $this->getMockBuilder(TransactionIdFactory::class)
            ->setMethods([
                'generateTransactionId',
            ])
            ->getMock();

        $generatedTransactionId = $this->faker->creditCardNumber;

        $mock->expects($this->once())
            ->method('generateTransactionId')
            ->will($this->returnValue($generatedTransactionId));

        $gateway->setTransactionIdFactory($mock);
        try{
            $gateway->purchase([]);
        }catch(InvalidRequestException $e){
            $this->setLastException($e);
        }
        $this->assertLastException(InvalidRequestException::class);
    }

    public function testPurchase(){
        return;
        $gateway = Omnipay::create("\\".OtpHuGateway::class);

        $gateway->setShopId($this->faker->randomNumber);
        $gateway->setPrivateKey($this->getDummyRsaPrivateKey());

        try {
            $response = $gateway->purchase([
                'amount' => '100.00',
                'currency' => 'HUF'
            ])->send();

            $response->getTransactionId(); // a reference generated by the payment gateway

            if ($response->isSuccessful()) {
                // payment was successful: update database
                /**
                 * ez sosem történhet meg, mert 3 szereplős fizetést használ az otp,
                 * ami azt jelenti, hogy nem mi kérjük be a bankkártya adatokat, hanem az otp oldala,
                 *
                 * így a terhelés sem sikerülhet anélkül, hogy át ne irányítanánk az otp oldalára
                 */
                print_r($response);
            } elseif ($response->isRedirect()) {
                // redirect to offsite payment gateway
                /**
                 * mindig redirectes választ fogunk kapni a ->puchase()-től, hiszen a háromszereplős fizetés miatt át kell irányítani a felhasználót az otp oldalára
                 */
                //$url = $response->getRedirectUrl();
                //$data = $response->getRedirectData(); // associative array of fields which must be posted to the redirectUrl

                echo 'REDIRECT NEEDED TO'."\n";
                $url = $response->getRedirectUrl();
                echo $url . "\n\n";


                //$response->redirect();
            } else {
                // payment failed: display message to customer
                /**
                 * az otp nem fogadta el a terhelési kérésünket
                 */
                echo $response->getMessage();
            }
        }
        catch(ValidationException $e){
            /**
             * hiányzó shopid, hiányzó vagy hibás private key, vagy hiányzó felhasználó adatok
             */
        }
        catch (Exception $e) {
            // internal error, log exception and display a generic message to the customer
            echo $e->getMessage();

            echo("\n\n".$e->getTraceAsString()."\n");
            exit("\n".'Sorry, there was an error processing your payment. Please try again later.');
        }

        return $response->getTransactionId();
    }
    /**
     * @depends testPurchase
     */
    public function testCompletePurchase($transactionId){
        return;
        $gateway = Omnipay::create("\\".OtpHuGateway::class);

        $gateway->setShopId("#02299991");
        $gateway->setPrivateKey(file_get_contents("#02299991.privKey.pem"));

        try {
            $response = $gateway->completePurchase([
                'transactionId' => $transactionId,
            ])->send();
            if ($response->isSuccessful()) {
                // payment was successful: update database
                echo 'SUCCESSFUL: ';
                print_r($response->getTransactionId());
            } else if ($response->isPending()){
                echo 'PENDING: ';
                print_r($response->getTransactionId());
            } else if ($response->isCancelled()){
                echo 'CANCELLED: ';
                print_r($response->getTransactionId());
            } else {
                // payment failed: display message to customer
                echo 'FAILED';
                echo $response->getMessage();
            }
        }
        catch (Exception $e) {
            // internal error, log exception and display a generic message to the customer
            //exit('Sorry, there was an error processing your payment. Please try again later.');
            throw $e;
        }
    }
}
