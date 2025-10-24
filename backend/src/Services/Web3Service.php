<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use React\EventLoop\LoopInterface;

class Web3Service
{
    private Logger $logger;
    private Client $httpClient;
    private LoopInterface $loop;
    private array $config;
    private array $walletConnections = [];
    private array $smartContracts = [];
    private array $nftCollections = [];
    private array $defiProtocols = [];
    private array $blockchainNetworks = [];
    private bool $isEnabled = false;
    private array $transactionHistory = [];
    private array $gasPriceCache = [];

    public function __construct(array $config, Logger $logger, LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        $this->initializeBlockchainNetworks();
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Web3 Service");
            
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("Web3 Service disabled by configuration");
                return true;
            }

            $this->isEnabled = true;
            $this->loadSmartContracts();
            $this->loadDefiProtocols();
            $this->startGasPriceMonitoring();
            
            $this->logger->info("Web3 Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Web3 Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function connectWallet(string $walletId, string $address, string $network = 'ethereum'): array
    {
        if (!$this->isEnabled) {
            return ['error' => 'Web3 service is disabled'];
        }

        try {
            $walletConnection = [
                'id' => $walletId,
                'address' => $address,
                'network' => $network,
                'connected_at' => microtime(true),
                'balance' => 0,
                'nonce' => 0,
                'status' => 'connected'
            ];

            $this->walletConnections[$walletId] = $walletConnection;

            // Fetch initial balance
            $balance = $this->getWalletBalance($address, $network);
            $this->walletConnections[$walletId]['balance'] = $balance;

            $this->logger->info("Wallet connected", [
                'wallet_id' => $walletId,
                'address' => $address,
                'network' => $network
            ]);

            return [
                'wallet_id' => $walletId,
                'address' => $address,
                'balance' => $balance,
                'network' => $network
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to connect wallet", [
                'wallet_id' => $walletId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to connect wallet'];
        }
    }

    public function getWalletBalance(string $address, string $network = 'ethereum'): float
    {
        try {
            $rpcUrl = $this->getRpcUrl($network);
            $response = $this->httpClient->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getBalance',
                    'params' => [$address, 'latest'],
                    'id' => 1
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $balanceHex = $data['result'] ?? '0x0';
            $balanceWei = hexdec($balanceHex);
            
            return $balanceWei / 1e18; // Convert from wei to ETH
        } catch (RequestException $e) {
            $this->logger->error("Failed to get wallet balance", [
                'address' => $address,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function sendTransaction(string $walletId, string $to, float $amount, array $options = []): array
    {
        if (!isset($this->walletConnections[$walletId])) {
            return ['error' => 'Wallet not connected'];
        }

        try {
            $wallet = $this->walletConnections[$walletId];
            $network = $wallet['network'];
            
            $gasPrice = $this->getGasPrice($network);
            $gasLimit = $options['gas_limit'] ?? 21000;
            
            $transaction = [
                'from' => $wallet['address'],
                'to' => $to,
                'value' => $this->toWei($amount),
                'gas' => '0x' . dechex($gasLimit),
                'gasPrice' => '0x' . dechex($gasPrice),
                'nonce' => '0x' . dechex($wallet['nonce']),
                'data' => $options['data'] ?? '0x'
            ];

            $txHash = $this->submitTransaction($transaction, $network);
            
            if ($txHash) {
                $this->walletConnections[$walletId]['nonce']++;
                
                $this->transactionHistory[] = [
                    'wallet_id' => $walletId,
                    'tx_hash' => $txHash,
                    'from' => $wallet['address'],
                    'to' => $to,
                    'amount' => $amount,
                    'network' => $network,
                    'timestamp' => microtime(true)
                ];

                $this->logger->info("Transaction sent", [
                    'wallet_id' => $walletId,
                    'tx_hash' => $txHash,
                    'amount' => $amount
                ]);

                return [
                    'tx_hash' => $txHash,
                    'status' => 'pending',
                    'amount' => $amount
                ];
            }

            return ['error' => 'Failed to submit transaction'];
        } catch (\Exception $e) {
            $this->logger->error("Failed to send transaction", [
                'wallet_id' => $walletId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to send transaction'];
        }
    }

    public function getTransactionStatus(string $txHash, string $network = 'ethereum'): array
    {
        try {
            $rpcUrl = $this->getRpcUrl($network);
            $response = $this->httpClient->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getTransactionReceipt',
                    'params' => [$txHash],
                    'id' => 1
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $receipt = $data['result'];

            if ($receipt) {
                return [
                    'tx_hash' => $txHash,
                    'status' => $receipt['status'] === '0x1' ? 'success' : 'failed',
                    'block_number' => hexdec($receipt['blockNumber']),
                    'gas_used' => hexdec($receipt['gasUsed']),
                    'transaction_fee' => hexdec($receipt['gasUsed']) * hexdec($receipt['effectiveGasPrice']) / 1e18
                ];
            }

            return [
                'tx_hash' => $txHash,
                'status' => 'pending',
                'block_number' => null
            ];
        } catch (RequestException $e) {
            $this->logger->error("Failed to get transaction status", [
                'tx_hash' => $txHash,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to get transaction status'];
        }
    }

    public function interactWithContract(string $walletId, string $contractAddress, string $method, array $params = [], array $options = []): array
    {
        if (!isset($this->walletConnections[$walletId])) {
            return ['error' => 'Wallet not connected'];
        }

        try {
            $wallet = $this->walletConnections[$walletId];
            $network = $wallet['network'];
            
            $contract = $this->getSmartContract($contractAddress, $network);
            if (!$contract) {
                return ['error' => 'Contract not found'];
            }

            $data = $this->encodeContractCall($contract, $method, $params);
            
            $transaction = [
                'from' => $wallet['address'],
                'to' => $contractAddress,
                'value' => $options['value'] ?? '0x0',
                'data' => $data,
                'gas' => $options['gas_limit'] ?? '0x' . dechex(100000),
                'gasPrice' => '0x' . dechex($this->getGasPrice($network)),
                'nonce' => '0x' . dechex($wallet['nonce'])
            ];

            $txHash = $this->submitTransaction($transaction, $network);
            
            if ($txHash) {
                $this->walletConnections[$walletId]['nonce']++;
                
                $this->logger->info("Contract interaction", [
                    'wallet_id' => $walletId,
                    'contract' => $contractAddress,
                    'method' => $method,
                    'tx_hash' => $txHash
                ]);

                return [
                    'tx_hash' => $txHash,
                    'status' => 'pending',
                    'contract' => $contractAddress,
                    'method' => $method
                ];
            }

            return ['error' => 'Failed to interact with contract'];
        } catch (\Exception $e) {
            $this->logger->error("Failed to interact with contract", [
                'wallet_id' => $walletId,
                'contract' => $contractAddress,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to interact with contract'];
        }
    }

    public function getNFTs(string $walletId, string $contractAddress = null): array
    {
        if (!isset($this->walletConnections[$walletId])) {
            return ['error' => 'Wallet not connected'];
        }

        try {
            $wallet = $this->walletConnections[$walletId];
            $nfts = [];

            if ($contractAddress) {
                // Get NFTs from specific contract
                $nfts = $this->getNFTsFromContract($wallet['address'], $contractAddress, $wallet['network']);
            } else {
                // Get all NFTs for the wallet
                foreach ($this->nftCollections as $collection) {
                    $collectionNFTs = $this->getNFTsFromContract($wallet['address'], $collection['address'], $wallet['network']);
                    $nfts = array_merge($nfts, $collectionNFTs);
                }
            }

            $this->logger->debug("NFTs retrieved", [
                'wallet_id' => $walletId,
                'count' => count($nfts)
            ]);

            return $nfts;
        } catch (\Exception $e) {
            $this->logger->error("Failed to get NFTs", [
                'wallet_id' => $walletId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to get NFTs'];
        }
    }

    public function getDefiPositions(string $walletId): array
    {
        if (!isset($this->walletConnections[$walletId])) {
            return ['error' => 'Wallet not connected'];
        }

        try {
            $wallet = $this->walletConnections[$walletId];
            $positions = [];

            foreach ($this->defiProtocols as $protocol) {
                $protocolPositions = $this->getProtocolPositions($wallet['address'], $protocol, $wallet['network']);
                $positions = array_merge($positions, $protocolPositions);
            }

            $this->logger->debug("DeFi positions retrieved", [
                'wallet_id' => $walletId,
                'count' => count($positions)
            ]);

            return $positions;
        } catch (\Exception $e) {
            $this->logger->error("Failed to get DeFi positions", [
                'wallet_id' => $walletId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to get DeFi positions'];
        }
    }

    public function getGasPrice(string $network = 'ethereum'): int
    {
        if (isset($this->gasPriceCache[$network])) {
            $cached = $this->gasPriceCache[$network];
            if (time() - $cached['timestamp'] < 60) { // Cache for 1 minute
                return $cached['price'];
            }
        }

        try {
            $rpcUrl = $this->getRpcUrl($network);
            $response = $this->httpClient->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_gasPrice',
                    'params' => [],
                    'id' => 1
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $gasPriceHex = $data['result'] ?? '0x0';
            $gasPrice = hexdec($gasPriceHex);

            $this->gasPriceCache[$network] = [
                'price' => $gasPrice,
                'timestamp' => time()
            ];

            return $gasPrice;
        } catch (RequestException $e) {
            $this->logger->error("Failed to get gas price", [
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            return 20000000000; // Default gas price (20 gwei)
        }
    }

    public function getWeb3Stats(): array
    {
        $totalWallets = count($this->walletConnections);
        $totalTransactions = count($this->transactionHistory);
        $totalContracts = count($this->smartContracts);
        $totalNFTs = 0;

        foreach ($this->nftCollections as $collection) {
            $totalNFTs += $collection['total_supply'] ?? 0;
        }

        return [
            'total_wallets' => $totalWallets,
            'total_transactions' => $totalTransactions,
            'total_contracts' => $totalContracts,
            'total_nfts' => $totalNFTs,
            'supported_networks' => array_keys($this->blockchainNetworks),
            'defi_protocols' => count($this->defiProtocols)
        ];
    }

    private function getRpcUrl(string $network): string
    {
        return $this->blockchainNetworks[$network]['rpc_url'] ?? '';
    }

    private function toWei(float $amount): string
    {
        return '0x' . dechex((int)($amount * 1e18));
    }

    private function submitTransaction(array $transaction, string $network): ?string
    {
        try {
            $rpcUrl = $this->getRpcUrl($network);
            $response = $this->httpClient->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_sendRawTransaction',
                    'params' => [$this->signTransaction($transaction)],
                    'id' => 1
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['result'] ?? null;
        } catch (RequestException $e) {
            $this->logger->error("Failed to submit transaction", [
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function signTransaction(array $transaction): string
    {
        // This would implement actual transaction signing
        // For now, return a mock signed transaction
        return '0x' . bin2hex(json_encode($transaction));
    }

    private function getSmartContract(string $address, string $network): ?array
    {
        return $this->smartContracts[$address] ?? null;
    }

    private function encodeContractCall(array $contract, string $method, array $params): string
    {
        // This would implement ABI encoding
        // For now, return a mock encoded call
        return '0x' . substr(md5($method . implode(',', $params)), 0, 8);
    }

    private function getNFTsFromContract(string $address, string $contractAddress, string $network): array
    {
        // This would query the NFT contract for tokens owned by the address
        // For now, return mock data
        return [
            [
                'token_id' => '1',
                'contract_address' => $contractAddress,
                'owner' => $address,
                'metadata_uri' => 'https://api.example.com/metadata/1',
                'name' => 'Example NFT #1'
            ]
        ];
    }

    private function getProtocolPositions(string $address, array $protocol, string $network): array
    {
        // This would query the DeFi protocol for user positions
        // For now, return mock data
        return [
            [
                'protocol' => $protocol['name'],
                'type' => 'liquidity_pool',
                'token_a' => 'ETH',
                'token_b' => 'USDC',
                'amount' => 1000,
                'value_usd' => 2000
            ]
        ];
    }

    private function initializeBlockchainNetworks(): void
    {
        $this->blockchainNetworks = [
            'ethereum' => [
                'name' => 'Ethereum',
                'chain_id' => 1,
                'rpc_url' => $this->config['ethereum_rpc_url'] ?? 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID',
                'explorer_url' => 'https://etherscan.io',
                'native_currency' => 'ETH'
            ],
            'polygon' => [
                'name' => 'Polygon',
                'chain_id' => 137,
                'rpc_url' => $this->config['polygon_rpc_url'] ?? 'https://polygon-rpc.com',
                'explorer_url' => 'https://polygonscan.com',
                'native_currency' => 'MATIC'
            ],
            'bsc' => [
                'name' => 'Binance Smart Chain',
                'chain_id' => 56,
                'rpc_url' => $this->config['bsc_rpc_url'] ?? 'https://bsc-dataseed.binance.org',
                'explorer_url' => 'https://bscscan.com',
                'native_currency' => 'BNB'
            ]
        ];
    }

    private function loadSmartContracts(): void
    {
        $this->smartContracts = [
            '0x1234567890123456789012345678901234567890' => [
                'address' => '0x1234567890123456789012345678901234567890',
                'name' => 'Example Token',
                'symbol' => 'EXT',
                'decimals' => 18,
                'abi' => []
            ]
        ];
    }

    private function loadDefiProtocols(): void
    {
        $this->defiProtocols = [
            [
                'name' => 'Uniswap V3',
                'address' => '0x1F98431c8aD98523631AE4a59f267346ea31F984',
                'type' => 'dex',
                'supported_tokens' => ['ETH', 'USDC', 'USDT', 'DAI']
            ],
            [
                'name' => 'Aave V3',
                'address' => '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
                'type' => 'lending',
                'supported_tokens' => ['ETH', 'USDC', 'USDT', 'DAI', 'WBTC']
            ]
        ];
    }

    private function startGasPriceMonitoring(): void
    {
        if (!$this->loop) {
            return;
        }

        // Update gas prices every 30 seconds
        $this->loop->addPeriodicTimer(30.0, function() {
            foreach (array_keys($this->blockchainNetworks) as $network) {
                $this->getGasPrice($network);
            }
        });
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function cleanup(): void
    {
        $this->walletConnections = [];
        $this->smartContracts = [];
        $this->nftCollections = [];
        $this->defiProtocols = [];
        $this->transactionHistory = [];
        $this->gasPriceCache = [];
        $this->isEnabled = false;
        $this->logger->info("Web3 Service cleaned up");
    }
}
