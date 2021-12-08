<?php

namespace Beycan\MultiChain;

use Web3\Web3;
use Web3\Eth;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use phpseclib\Math\BigInteger as BigNumber;

final class Provider
{
    /**
     * Web3 instance
     * @var Web3
     */
    private static $web3;

    /**
     * Current blockchain gas price
     * @var int
     */
    private $defaultGasPrice = 10000000000;

    /**
     * Current blockchain transfer nonce
     * @var int
     */
    private $defaultNonce = 1;

    /**
     * Current blockchain native currency
     * @var object
     */
    private $currency;

    /**
     * Eth instance / RPC Api methods
     * @var Eth
     */
    public $methods;

    /**
     * @param string $rpcUrl
     * @param array $currency
     * @param integer $timeOut
     * @throws Exception
     */
    public function __construct(string $rpcUrl, array $currency, int $timeOut = 5)
    {
        if (!isset($currency['symbol']) || !isset($currency['decimals'])) {
            throw new \Exception("It should contain native currency, symbol and decimals values", 18000);
        }

        $this->currency = (object) $currency;

        self::$web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, $timeOut)));
        $this->methods = self::$web3->eth;
    }

    /**
     * Runs the signed transaction
     * @param string $signedTransaction
     * @return string
     * @throws Exception
     */
    public function sendSignedTransaction(string $signedTransaction) : string
    {
        $transactionId = null;
        $this->methods->sendRawTransaction('0x'. $signedTransaction, function($err, $tx) use (&$transactionId) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $transactionId = $tx;
            }
        });

        if (is_string($transactionId)) {
            return $transactionId;
        } else {
            throw new \Exception("There was a problem retrieving the transaction id!", 13000);
        }
    }

    /**
     * Gets the chain id of the blockchain network given the RPC url address
     * @return int
     * @throws Exception
     */
    public function getChainId() : int
    {
        $chainId = null;
        self::$web3->net->version(function($err, $res) use (&$chainId) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $chainId = $res;
            }
        });

        
        if (is_string($chainId)) {
            return intval($chainId);
        } else {
            throw new \Exception("There was a problem retrieving the chain id!", 14000);
        }
    }

    /**
     * It receives the gas fee required for the transactions
     * @return string
     * @throws Exception
     */
    public function getGasPrice() : string
    {
        $result = null;
        $this->methods->gasPrice(function($err, $res) use (&$result) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $result = $res;
            }
        });

        if ($result instanceof BigNumber) {
            return Utils::hex($result->toString());
        } else {
            return Utils::hex($this->defaultGasPrice);
        }
    }

    /**
     * Get transfer nonce
     * @param string $from
     * @return string
     * @throws Exception
     */
    public function getNonce(string $from) : string
    {
        $result = null;
        $this->methods->getTransactionCount($from, 'pending', function($err, $res) use (&$result) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $result = $res;
            }
        });
        
        if ($result instanceof BigNumber) {
            return Utils::hex($result->toString());
        } else {
            return Utils::hex($this->defaultNonce);
        }
    }

    /**
     * @return object
     */
    public function getCurrency() : object
    {
        return $this->currency;
    }

    /**
     * @return HttpProvider
     */
    public static function getHttpProvider() : HttpProvider
    {
        return self::$web3->provider;
    }
}