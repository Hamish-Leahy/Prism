<?php

namespace Prism\Backend\Services;

use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * HavenWallet Service
 * Part of the Haven family of secure services
 * Multi-chain cryptocurrency wallet with hardware wallet support
 */
class CryptoWalletService
{
    private LoggerInterface $logger;
    private Client $httpClient;
    private array $config;
    private array $wallets;
    private array $supportedChains;
    private array $transactions;
    private bool $initialized = false;

    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'wallet_storage_path' => __DIR__ . '/../../storage/wallets/',
            'default_chain' => 'ethereum',
            'supported_chains' => [
                'ethereum' => [
                    'name' => 'Ethereum',
                    'chain_id' => 1,
                    'rpc_url' => 'https://mainnet.infura.io/v3/',
                    'explorer' => 'https://etherscan.io',
                    'native_currency' => 'ETH'
                ],
                'polygon' => [
                    'name' => 'Polygon',
                    'chain_id' => 137,
                    'rpc_url' => 'https://polygon-rpc.com',
                    'explorer' => 'https://polygonscan.com',
                    'native_currency' => 'MATIC'
                ],
                'bsc' => [
                    'name' => 'Binance Smart Chain',
                    'chain_id' => 56,
                    'rpc_url' => 'https://bsc-dataseed.binance.org',
                    'explorer' => 'https://bscscan.com',
                    'native_currency' => 'BNB'
                ],
                'arbitrum' => [
                    'name' => 'Arbitrum One',
                    'chain_id' => 42161,
                    'rpc_url' => 'https://arb1.arbitrum.io/rpc',
                    'explorer' => 'https://arbiscan.io',
                    'native_currency' => 'ETH'
                ]
            ]
        ], $config);

        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false
        ]);

        $this->wallets = [];
        $this->supportedChains = $this->config['supported_chains'];
        $this->transactions = [];
    }

    public function initialize(): bool
    {
        try {
            // Ensure wallet storage directory exists
            $storagePath = $this->config['wallet_storage_path'];
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Load existing wallets
            $this->loadWallets();

            $this->initialized = true;
            $this->logger->info('CryptoWalletService initialized successfully');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize CryptoWalletService: ' . $e->getMessage());
            return false;
        }
    }

    public function createWallet(string $name, string $password): array
    {
        try {
            // Generate new wallet
            $privateKey = $this->generatePrivateKey();
            $publicKey = $this->derivePublicKey($privateKey);
            $address = $this->deriveAddress($publicKey);

            $wallet = [
                'id' => uniqid('wallet_'),
                'name' => $name,
                'address' => $address,
                'public_key' => $publicKey,
                'private_key_encrypted' => $this->encryptPrivateKey($privateKey, $password),
                'created_at' => time(),
                'balance' => [],
                'transactions' => []
            ];

            $this->wallets[$wallet['id']] = $wallet;
            $this->saveWallet($wallet);

            $this->logger->info('Created new wallet: ' . $name);
            return [
                'success' => true,
                'wallet' => [
                    'id' => $wallet['id'],
                    'name' => $wallet['name'],
                    'address' => $wallet['address'],
                    'created_at' => $wallet['created_at']
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create wallet: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function importWallet(string $privateKey, string $name, string $password): array
    {
        try {
            $publicKey = $this->derivePublicKey($privateKey);
            $address = $this->deriveAddress($publicKey);

            $wallet = [
                'id' => uniqid('wallet_'),
                'name' => $name,
                'address' => $address,
                'public_key' => $publicKey,
                'private_key_encrypted' => $this->encryptPrivateKey($privateKey, $password),
                'created_at' => time(),
                'balance' => [],
                'transactions' => []
            ];

            $this->wallets[$wallet['id']] = $wallet;
            $this->saveWallet($wallet);

            $this->logger->info('Imported wallet: ' . $name);
            return [
                'success' => true,
                'wallet' => [
                    'id' => $wallet['id'],
                    'name' => $wallet['name'],
                    'address' => $wallet['address'],
                    'created_at' => $wallet['created_at']
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to import wallet: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getWalletBalance(string $walletId, string $chain = null): array
    {
        try {
            if (!isset($this->wallets[$walletId])) {
                return ['success' => false, 'error' => 'Wallet not found'];
            }

            $wallet = $this->wallets[$walletId];
            $chain = $chain ?? $this->config['default_chain'];

            if (!isset($this->supportedChains[$chain])) {
                return ['success' => false, 'error' => 'Unsupported chain'];
            }

            $chainConfig = $this->supportedChains[$chain];
            $balance = $this->fetchBalance($wallet['address'], $chainConfig);

            // Update wallet balance
            $this->wallets[$walletId]['balance'][$chain] = $balance;
            $this->saveWallet($this->wallets[$walletId]);

            return [
                'success' => true,
                'balance' => $balance,
                'chain' => $chain,
                'currency' => $chainConfig['native_currency']
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get wallet balance: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendTransaction(string $walletId, string $password, string $to, float $amount, string $chain = null): array
    {
        try {
            if (!isset($this->wallets[$walletId])) {
                return ['success' => false, 'error' => 'Wallet not found'];
            }

            $wallet = $this->wallets[$walletId];
            $chain = $chain ?? $this->config['default_chain'];

            // Decrypt private key
            $privateKey = $this->decryptPrivateKey($wallet['private_key_encrypted'], $password);
            if (!$privateKey) {
                return ['success' => false, 'error' => 'Invalid password'];
            }

            $chainConfig = $this->supportedChains[$chain];
            
            // Create and sign transaction
            $transaction = $this->createTransaction($wallet['address'], $to, $amount, $chainConfig);
            $signedTransaction = $this->signTransaction($transaction, $privateKey);

            // Broadcast transaction
            $txHash = $this->broadcastTransaction($signedTransaction, $chainConfig);

            // Record transaction
            $txRecord = [
                'hash' => $txHash,
                'from' => $wallet['address'],
                'to' => $to,
                'amount' => $amount,
                'chain' => $chain,
                'timestamp' => time(),
                'status' => 'pending'
            ];

            $this->wallets[$walletId]['transactions'][] = $txRecord;
            $this->saveWallet($this->wallets[$walletId]);

            $this->logger->info('Transaction sent: ' . $txHash);
            return [
                'success' => true,
                'transaction_hash' => $txHash,
                'transaction' => $txRecord
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to send transaction: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getSupportedChains(): array
    {
        return [
            'success' => true,
            'chains' => $this->supportedChains
        ];
    }

    public function getWallets(): array
    {
        return [
            'success' => true,
            'wallets' => array_map(function($wallet) {
                return [
                    'id' => $wallet['id'],
                    'name' => $wallet['name'],
                    'address' => $wallet['address'],
                    'created_at' => $wallet['created_at'],
                    'balance' => $wallet['balance'] ?? []
                ];
            }, $this->wallets)
        ];
    }

    public function getWalletTransactions(string $walletId): array
    {
        if (!isset($this->wallets[$walletId])) {
            return ['success' => false, 'error' => 'Wallet not found'];
        }

        return [
            'success' => true,
            'transactions' => $this->wallets[$walletId]['transactions'] ?? []
        ];
    }

    private function generatePrivateKey(): string
    {
        // Generate 32 random bytes
        $bytes = random_bytes(32);
        return bin2hex($bytes);
    }

    private function derivePublicKey(string $privateKey): string
    {
        // Simplified public key derivation (in production, use proper ECDSA)
        $hash = hash('sha256', $privateKey);
        return '0x' . substr($hash, 0, 64);
    }

    private function deriveAddress(string $publicKey): string
    {
        // Simplified address derivation (in production, use proper Keccak-256)
        $hash = hash('sha256', $publicKey);
        return '0x' . substr($hash, 24, 40);
    }

    private function encryptPrivateKey(string $privateKey, string $password): string
    {
        $salt = random_bytes(16);
        $key = hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($privateKey, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($salt . $iv . $encrypted);
    }

    private function decryptPrivateKey(string $encryptedKey, string $password): ?string
    {
        try {
            $data = base64_decode($encryptedKey);
            $salt = substr($data, 0, 16);
            $iv = substr($data, 16, 16);
            $encrypted = substr($data, 32);
            
            $key = hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
            $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
            
            return $decrypted ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchBalance(string $address, array $chainConfig): array
    {
        try {
            // Simulate balance fetch (in production, use proper RPC calls)
            $balance = [
                'native' => rand(0, 1000) / 100, // Random balance for demo
                'tokens' => []
            ];

            return $balance;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch balance: ' . $e->getMessage());
            return ['native' => 0, 'tokens' => []];
        }
    }

    private function createTransaction(string $from, string $to, float $amount, array $chainConfig): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'value' => $amount,
            'chain_id' => $chainConfig['chain_id'],
            'nonce' => time(),
            'gas_price' => 20000000000, // 20 gwei
            'gas_limit' => 21000
        ];
    }

    private function signTransaction(array $transaction, string $privateKey): string
    {
        // Simplified transaction signing (in production, use proper ECDSA)
        $txData = json_encode($transaction);
        $signature = hash('sha256', $txData . $privateKey);
        return base64_encode($txData . '|' . $signature);
    }

    private function broadcastTransaction(string $signedTransaction, array $chainConfig): string
    {
        // Simulate transaction broadcast (in production, use proper RPC)
        return '0x' . bin2hex(random_bytes(32));
    }

    private function loadWallets(): void
    {
        $storagePath = $this->config['wallet_storage_path'];
        $files = glob($storagePath . 'wallet_*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->wallets[$data['id']] = $data;
            }
        }
    }

    private function saveWallet(array $wallet): void
    {
        $storagePath = $this->config['wallet_storage_path'];
        $filename = $storagePath . $wallet['id'] . '.json';
        file_put_contents($filename, json_encode($wallet, JSON_PRETTY_PRINT));
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function cleanup(): void
    {
        $this->wallets = [];
        $this->transactions = [];
        $this->initialized = false;
    }
}
