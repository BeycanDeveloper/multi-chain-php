<?php

namespace Beycan\MultiChain;

use Web3\Validators\AddressValidator;
use phpseclib\Math\BigInteger as BigNumber;

final class Token
{
    /**
     * Provider
     * @var Provider
     */
    private $provider;

    /**
     * Current token contract address
     * @var string
     */
    private $address;

    /**
     * Default gas
     * @var int
     */
    private $defaultGas = 50000;

    /**
     * Current token contract
     * @var Web3Contract
     */
    public $contract;

    /**
     * @param string $tokenAddress
     * @param array $abi
     */
    public function __construct(string $tokenAddress, array $abi = [])
    {
        if (AddressValidator::validate($tokenAddress) === false) {
            throw new \Exception('Invalid token address!', 23000);
        }

        $this->address = $tokenAddress;
        $this->provider = MultiChain::getProvider();
        $abi = empty($abi) ? file_get_contents(dirname(__DIR__) . '/resources/abi.json') : $abi;
        $this->contract = (new Contract($tokenAddress, json_decode($abi)));
    }

    /**
     * Generates a token transfer data
     *
     * @param string $from
     * @param string $to
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function transferData(string $from, string $to, float $amount) : array
    {
        if ($this->getBalance($from) < $amount) {
            throw new \Exception('Insufficient balance!', 10000);
        }
        
        $hexAmount = Utils::toHex($amount, $this->getDecimals());
        return [
            'from' => $from,
            'value' => '0x0',
            'to' => $this->address,
            'chainId' => $this->provider->getChainId(),
            'nonce' => $this->provider->getNonce($from),
            'gasPrice' => $this->provider->getGasPrice(),
            'gas' => $this->getEstimateGas($from, $to, $amount),
            'data' => '0x' . $this->contract->getData('transfer', $to, $hexAmount),
        ];
    }

    /**
     * Calculates estimated gas for the transfer transaction
     *
     * @param string $from
     * @param string $to
     * @param float $amount
     * @return string
     * @throws Exception
     */
    public function getEstimateGas(string $from, string $to, float $amount) : string
    {
        $amount = Utils::toHex($amount, $this->getDecimals());
        $result = $this->contract->getEstimateGas('transfer', $to, $amount, ['from' => $from]);

        if ($result instanceof BigNumber) {
            return Utils::hex($result->toString());
        } else {
            return Utils::hex($this->defaultGas);
        }
    }

    /**
     * Returns the token's decimals
     * @return int
     * @throws Exception
     */
    public function getDecimals() : int
    {
        $result = $this->contract->decimals();

        if (is_array($result) && $result[0] instanceof BigNumber) {
            return intval($result[0]->toString());
        } else {
            throw new \Exception("There was a problem retrieving the decimals value!", 12000);
        }
    }

    /**
     * Returns the balance of the current token in the address given wallet address
     *
     * @param string $address
     * @return float
     * @throws Exception
     */
    public function getBalance(string $address) : float
    {
        $result = $this->contract->balanceOf($address);

        if (is_array($result) && $result['balance'] instanceof BigNumber) {
            return Utils::toDec($result['balance']->toString(), $this->getDecimals());
        } else {
            throw new \Exception("There was a problem retrieving the balance!", 11000);
        }
    }

    /**
     * get token name
     *
     * @return string|null
     * @throws Exception
     */
    public function getName() : ?string
    {
        return $this->contract->name();
    }

    /**
     * get token symbol
     *
     * @return string|null
     * @throws Exception
     */
    public function getSymbol() : ?string
    {
        return $this->contract->symbol();
    }

    /**
     * get token total supply
     *
     * @return string
     * @throws Exception
     */
    public function getTotalSupply() : string
    {
        $totalSupply = $this->contract->totalSupply();
        
        if (is_array($totalSupply) && end($totalSupply) instanceof BigNumber) {
            $totalSupply = Utils::toDec(end($totalSupply)->toString(), 
            $this->getDecimals());
            return number_format($totalSupply, $this->getDecimals(), ',', '.');
        } else {
            throw new \Exception("There was a problem retrieving the total suppy!", 14001);
        }
    }

    /**
     * Returns the current token contract address
     * @return string
     */
    public function getAddress() : string
    {
        return $this->address;
    }

    /**
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        return $this->contract->$name(...$args);
    }
}