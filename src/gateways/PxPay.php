<?php

namespace platocreative\paymentexpress\gateways;

use craft\helpers\App;
use platocreative\paymentexpress\models\RequestResponse;
use platocreative\paymentexpress\events\CreateGatewayEvent;


use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\Transaction;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\omnipay\base\OffsiteGateway;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Omnipay;
use Omnipay\PaymentExpress\PxPayGateway;
use Omnipay\PaymentExpress\PxPostGateway;
use yii\base\NotSupportedException;

/**
 * Gateway represents PaymentExpress gateway
 *
 * @author    Plato Creative. <web@platocreative.co.nz>
 * @since     1.1.4
 */

class PxPay extends OffsiteGateway
{

    // Constants
    // =========================================================================
    const EVENT_BEFORE_CREATE_GATEWAY = 'beforeCreateGateway';

    // Properties
    // =========================================================================
    /**
     * @var string
     */
    public $username;
    /**
     * @var string
     */
    public $password;
    /**
     * @var string
     */
    public $pxPostUsername;
    /**
     * @var string
     */
    public $pxPostPassword;
    /**
     * @var string
     */
    public $vendor;
    /**
     * @var bool
     */
    public $testMode;
    /**
     * @var bool
     */
    public $enableRefunds;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Payment Express');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('payment-express-for-craft-commerce-2/gatewaySettings', ['gateway' => $this]);
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsRefund(): bool
    {
        if (is_null($this->enableRefunds)) {
            return false;
        }
        return $this->enableRefunds;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var Gateway $gateway */
        $gateway = $this->getGateway();
        $gateway->setTestMode($this->testMode);

        $event = new CreateGatewayEvent([
            'gateway' => $gateway
        ]);

        // Raise 'beforeCreateGateway' event
        $this->trigger(self::EVENT_BEFORE_CREATE_GATEWAY, $event);

        return $event->gateway;
    }

    protected function getGateway()
    {
        $gatewayName = '\\'.PxPayGateway::class;
        $username = Craft::parseEnv($this->username);
        $password = Craft::parseEnv($this->password);

        // swap the gateway to PxPost if running a refund
        $actionSegments = Craft::$app->getRequest()->actionSegments;
        $action = array_pop($actionSegments);
        if ($action === 'transaction-refund') {
            $gatewayName = '\\'.PxPostGateway::class;
            $username = Craft::parseEnv($this->pxPostUsername);
            $password = Craft::parseEnv($this->pxPostPassword);
        }

        $gateway = Omnipay::create($gatewayName);
        $gateway->setUsername($username);
        $gateway->setPassword($password);

        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName()
    {
        // swap the gateway to PxPost if running a refund
        $requestBody = Craft::$app->getRequest()->bodyParams;
        if (isset($requestBody['action']) && strpos($requestBody['action'], 'transaction-refund')) {
            return '\\'.PxPostGateway::class;
        }

        return '\\'.PxPayGateway::class;
    }

    /**
     * @inheritdoc
     */
    protected function createPaymentRequest(Transaction $transaction, $card = null, $itemBag = null): array
    {
        $request = parent::createPaymentRequest($transaction, $card, $itemBag);

        if(strlen($transaction->hash) > 16) {
            $shortenedHash = substr($transaction->hash, 0, 16);
        } else {
            $shortenedHash = $transaction->hash;
        }

        $request['transactionId'] = $shortenedHash;

        return $request;

    }

    /**
     * @inheritdoc
     */
    protected function prepareResponse(ResponseInterface $response, Transaction $transaction): RequestResponseInterface
    {
        return new RequestResponse($response, $transaction);
    }


}
