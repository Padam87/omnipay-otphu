<?php
namespace Clapp\OtpHu\Request;

use LSS\Array2XML;
use Guzzle\Http\Message\Response as GuzzleResponse;
use Clapp\OtpHu\Response\PaymentResponse;


class PaymentRequest extends AbstractRequest{

    protected $actionName = 'WEBSHOPFIZETESINDITAS';

    public function getData(){

        $variablesData = [
            'isClientCode' => 'WEBSHOP', // ?
            'isPOSID' => $this->getShopId(),
            'isTransactionID' => $this->getTransactionId(),
            'isAmount' => $this->getAmount(),
            'isExchange' => $this->getCurrency(),
            'isLanguageCode' => $this->getLanguage(),
            'isCardPocketId' => '',
            'isNameNeeded' => false,
            'isCountryNeeded' => false,
            'isCountyNeeded' => false,
            'isSettlementNeeded' => false,
            'isZipcodeNeeded' => false,
            'isStreetNeeded' => false,
            'isMailAddressNeeded' => false,
            'isNarrationNeeded' => false,
            'isConsumerReceiptNeeded' => false,
            'isBackURL' => 'http://www.google.com',
            'isShopComment' => '',
            'isConsumerRegistrationNeeded' => false,
            //'isConsumerRegistrationID' => null,
        ];

        $variables = [];

        foreach($variablesData as $key => $value){
            $variables[$key] = [
                '@value' => $value,
            ];
        }

        $variables['isClientSignature'] = [
           '@attributes' => [
                "algorithm" => "SHA512"
            ],
            '@value' => $this->generateSignature()
        ];

        $signedActionBody = Array2XML::createXML('StartWorkflow',
            [
                'TemplateName' => [
                    '@value' => $this->actionName,
                ],
                'Variables' => $variables,
            ]
        );
        return $this->createSoapEnvelope($this->actionName, $signedActionBody);
    }
    /**
     * aláírandó string összeállítása
     * ( 2.4.3.1 A digitális aláírás képzése )
     */
    protected function getSignatureData(){
        /**
         * •    Háromszereplős fizetési tranzakcó esetén:
                o   shop-azonosító
                o   tranzakcióazonosító
                o   összeg
                o   devizanem
                o   regisztrált/regisztrálandó ügyfél azonosítója (csak regisztrált típusú fizetéskor)

         */
        $data = [
            $this->getShopId(),
            $this->getTransactionId(),
            $this->getAmount(),
            $this->getCurrency(),
            "", //placeholder, nem használt feature miatt
        ];
        return implode("|", $data);
    }

    public function transformResponse(GuzzleResponse $response){
        return new PaymentResponse($this, $response->getBody());
    }

    public function getLanguage(){
        $value = $this->getParameter('language');
        if (empty($value)) $value = "hu";
        return $value;
    }
    public function setLanguage($value){
        return $this->setParameter('language', $value);
    }
    /**
     * Get the number of decimal places in the payment currency.
     *
     * @return integer
     */
    public function getCurrencyDecimalPlaces()
    {
        if ($this->getCurrency() == "HUF"){
            return 0;
        }
        return parent::getCurrencyDecimalPlaces();
    }

    /**
     * Format an amount for the payment currency.
     *
     * @return string
     */
    public function formatCurrency($amount)
    {
        return number_format(
            $amount,
            $this->getCurrencyDecimalPlaces(),
            ',',
            ''
        );
    }
}
