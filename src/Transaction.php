<?php

namespace Beycan\MultiChain;

use Web3\Validators\AddressValidator;
use Web3\Validators\BlockHashValidator;
use Web3\Validators\TransactionValidator;

final class Transaction
{
    /**
     * Provider
     * @var Provider
     */
    private $provider;
    
    /**
     * Transaction id
     * @var string
     */
    private $id;

    /**
     * Transaction data
     * @var object
     */
    private $data;

    /**
     * @param string $transactionId
     * @throws Exception
     */
    public function __construct(string $transactionId)
    {
        if (BlockHashValidator::validate($transactionId) === false) {
            throw new \Exception('Invalid transaction id!', 24000);
        }

        $this->id = $transactionId;
        $this->provider = MultiChain::getProvider();

        $this->provider->methods->getTransactionByHash($this->id, function($err, $tx){
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                if (TransactionValidator::validate((array)$tx) === false) {
                    throw new \Exception('Invalid transaction data!', 25000);
                } else {
                    $this->data = $tx;
                }
            }
        });

        $this->provider->methods->getTransactionReceipt($this->id, function($err, $tx){
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                if (TransactionValidator::validate((array)$tx) === false) {
                    throw new \Exception('Invalid transaction data!', 25000);
                } else {
                    $this->data->status = $tx->status;
                }
            }
        });
    }

    /**
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * @return object|null
     */
    public function getData() : ?object
    {
        return $this->data;
    }

    /**
     * @return object|null
     */
    public function decodeInput() : ?object
    {
        $input = $this->data->input;
        $pattern = '/.+?(?=000000000000000000000000)/';
        preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE, 0);
        $method = $matches[0][0];

        if ($input != '0x') {
            $input = str_replace($method, '', $input);
            $receiver = '0x' . ltrim(substr($input, 0, 64), 0);
            $amount = '0x' . ltrim(substr($input, 64), 0);
            return (object) compact('receiver', 'amount');
        } else {
            return null;
        }
    }

    /**
     * @param string|null $tokenAddress
     * @return string
     */
    public function verify(?string $tokenAddress = null) : string
    {
        if (!is_null($tokenAddress) && AddressValidator::validate($tokenAddress) === false) {
            throw new \Exception('Invalid token address!', 23000);
        }

        if ($this->data == null) {
            return 'failed';
        } else {
            if ($this->data->blockNumber !== null) {
                if ($this->data->status == '0x0') {
                    return 'failed';
                } elseif (!is_null($tokenAddress) && $this->data->input == '0x') {
                    return 'failed';
                } elseif (is_null($tokenAddress) && $this->data->value == '0x0') {
                    return 'failed';
                } else {
                    return 'verified';
                }
            } else {
                return 'pending';
            }
        }
    }

    /**
     * @param string $receiver
     * @param float $amount
     * @param string|null $tokenAddress
     * @return string
     */
    public function verifyData(string $receiver, float $amount, ?string $tokenAddress = null) : string
    {
        $receiver = strtolower($receiver);
        if (AddressValidator::validate($receiver) === false) {
            throw new \Exception('Invalid receiver address!', 22000);
        }

        if ($amount <= 0) {
            throw new \Exception("The amount cannot be zero or less than zero!", 20000);
        } 

        if (!is_null($tokenAddress)) {
            if (AddressValidator::validate($tokenAddress) === false) {
                throw new \Exception('Invalid token address!', 23000);
            }
        }

        if (is_null($tokenAddress)) {

            $data = (object) [
                'receiver' => strtolower($this->data->to),
                'amount' => Utils::toDec($this->data->value, (new Coin())->getDecimals())
            ];

            if ($data->receiver == $receiver && strval($data->amount) == strval($amount)) {
                return 'verified';
            }

        } else {

            $decodedInput = $this->decodeInput();
            
            $data = (object) [
                'receiver' => strtolower($decodedInput->receiver),
                'amount' => Utils::toDec($decodedInput->amount, (new Token($tokenAddress))->getDecimals())
            ];

            if ($data->receiver == $receiver && strval($data->amount) == strval($amount)) {
                return 'verified';
            }
        }

        return 'failed';
    }

    /**
     * @param string $receiver
     * @param float $amount
     * @param string|null $tokenAddress
     * @return string
     */
    public function verifyWithData(string $receiver, float $amount, ?string $tokenAddress = null) : string
    {
        $result = $this->verify($tokenAddress);
        if ($result == 'verified') {
            return $this->verifyData($receiver, $amount, $tokenAddress);
        } else {
            return $result;
        }
    }
}