<?php

namespace App\Helpers;

use RuntimeException;

class CardanoCliWrapper
{
    private string $cliPath;
    private string $socketPath;

    public int $sourceLovelace;

    public string $txPrefix;
    
    public int $transactionFee;

    public function __construct(string $txPrefix, string $cliPath = '/usr/local/bin/cardano-cli', string $socketPath = '/tmp/node.socket')
    {
        $this->cliPath = $cliPath;
        $this->txPrefix = $txPrefix;
        $this->socketPath = $socketPath;
        $this->transactionFee = 0;
        $this->sourceLovelace = 0;
    }

    private function run(array $args): string
    {
        $cmd = $this->cliPath . ' ' . implode(' ', $args);

        if (in_array('tip', $args, true)) {
            $cmd .= ' | jq .slot? ';
        }
        
        if (in_array('build', $args, true)) {

            // dd($cmd);

            // Security check, the source has to get all ADA back, if babel fees are used.

            if (($this->sourceLovelace > 0) && !str_contains($cmd, $this->sourceLovelace)) {
                // throw new RuntimeException("Cardano-CLI failed:\nCommand: $cmd\nOutput:\n" . $this->sourceLovelace);
            }

            // dd($this->sourceLovelace, $cmd);            
        }
        
        $cmd .= ' 2>&1';

        $output = [];
        $ret    = 0;

        exec($cmd, $output, $ret);

        if ($ret !== 0) {
            throw new RuntimeException("Cardano-CLI failed:\nCommand: $cmd\nOutput:\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    public function queryProtocolParams(string $outFile): void
    {
        $this->run([
            'query', 'protocol-parameters',
            '--mainnet',
            '--socket-path', $this->socketPath,
            '--out-file', $outFile
        ]);
    }

    public function calculateMinRequiredUtxo(string $tout, string $protocolParamsFile): ?int
    {
        $str = $this->run([
            'conway', 'transaction', 
            'calculate-min-required-utxo',
            '--protocol-params-file', $protocolParamsFile,
            '--tx-out', $tout            
        ]);

        if (preg_match('/\d+/', $str, $matches)) {
           return (int)$matches[0];
        }
        
        return null;
    }

    public function getSlotNumber(int $slotDistance = 1000): ?int
    {
        $str = $this->run([
            'query', 'tip',
            '--mainnet',
            '--socket-path', $this->socketPath
        ]);

        if (preg_match('/\d+/', $str, $matches)) {
           return (int)$matches[0] + $slotDistance;
        }
                
        return null;
    }

    public function buildTransaction(array $txins, array $txout, string $changeAddress, string $outFile, int $sourceLovelace = 0, string $metaData = '', string $mintingScript = ''): ?string
    {
        $txinsString = implode(' ', $txins);
        $txoutString = implode(' ', $txout);

        $this->sourceLovelace = $sourceLovelace;

        if (!empty($mintingScript)) {
            file_put_contents($this->txPrefix.'policy.script', $mintingScript);
        }

        if (!empty($metaData)) {
            file_put_contents($this->txPrefix.'metadata.json', $metaData);
        }

        if (strlen($txinsString) && strlen($changeAddress)) {
        
            if (strlen($mintingScript) && strlen($metaData)) {
                $str = $this->run([
                    'conway', 'transaction',
                    'build',  '--mainnet',
                    '--socket-path', $this->socketPath,
                    '--witness-override',  '2',
                    $txinsString,
                    $txoutString,
                    '--change-address', $changeAddress,
                    '--minting-script-file',  $this->txPrefix.'policy.script',
                    '--metadata-json-file',  $this->txPrefix.'metadata.json',
                    '--invalid-hereafter', $this->getSlotNumber(),            
                    '--out-file', $this->txPrefix.$outFile
                ]);

            } else if (strlen($mintingScript) && !strlen($metaData)) {
                $str = $this->run([
                    'conway', 'transaction',
                    'build',  '--mainnet',
                    '--socket-path', $this->socketPath,
                    '--witness-override',  '2',
                    $txinsString,
                    $txoutString,
                    '--change-address', $changeAddress,
                    '--minting-script-file',  $this->txPrefix.'policy.script',
                    '--invalid-hereafter', $this->getSlotNumber(),            
                    '--out-file', $this->txPrefix.$outFile
                ]);

            } else if (!strlen($mintingScript) && strlen($metaData)) {
                $str = $this->run([
                    'conway', 'transaction',
                    'build',  '--mainnet',
                    '--socket-path', $this->socketPath,
                    '--witness-override',  '2',
                    $txinsString,
                    $txoutString,
                    '--change-address', $changeAddress,
                    '--metadata-json-file',  $this->txPrefix.'metadata.json',                    
                    '--invalid-hereafter', $this->getSlotNumber(),
                    '--out-file', $this->txPrefix.$outFile
                ]);

            } else {
                $str = $this->run([
                    'conway', 'transaction', 
                    'build',  '--mainnet',
                    '--socket-path', $this->socketPath,
                   '--witness-override',  '2',
                    $txinsString,
                    $txoutString,
                    '--change-address', $changeAddress,
                    '--invalid-hereafter', $this->getSlotNumber(),
                    '--out-file', $this->txPrefix.$outFile
                ]);
            }
        
            if (str_contains($str, 'Estimated') && preg_match('/(\d+)/', $str, $matches)) {
                $this->transactionFee = (int)$matches[1];
                return $matches[1];
            }
        }
        
        return null;
    }

    public function witnessTransaction($skey, $signer): void
    {
        $signingKeyFile = $this->txPrefix.'TRXNK';

        file_put_contents($signingKeyFile, $skey);
        
        $txBodyFile = $this->txPrefix.'matx.raw';

        $outFile = $this->txPrefix.'witness.'.$signer;
        
         $str = $this->run([
            'conway', 'transaction', 
            'witness', '--mainnet',
            '--signing-key-file', $signingKeyFile,
            '--tx-body-file', $txBodyFile,
            '--out-file', $outFile            
        ]);
        
        if (file_exists($signingKeyFile)) {
            unlink($signingKeyFile);
        }
    }

     public function signTransaction($skey): void
    {
        $signingKeyFile = $this->txPrefix.'TRXNK';

        file_put_contents($signingKeyFile, $skey);
        
        $txBodyFile = $this->txPrefix.'matx.raw';

        $outFile = $this->txPrefix.'matx.signed';
        
         $str = $this->run([
            'conway', 'transaction', 
            'sign', '--mainnet',
            '--signing-key-file', $signingKeyFile,
            '--tx-body-file', $txBodyFile,
            '--out-file', $outFile            
        ]);
        
        if (file_exists($signingKeyFile)) {
            unlink($signingKeyFile);
        }
    }

    public function submitTransaction(): ?string
    {
        $inp = $this->txPrefix.'matx.signed';

        $str = $this->run([
            'conway', 'transaction', 
            'submit', '--mainnet',
            '--socket-path', $this->socketPath,
            '--tx-file', $inp
        ]);
                
        $jsonPart = trim(substr($str, strpos($str, '{')));

        $dataArray = json_decode($jsonPart, true);
        
        return $dataArray['txhash'] ?? null;
    }

    public function assembleSignature(): void
    {   
        $directory = $this->txPrefix;

        $wt1 = $directory.'witness.provider';

        $wt2 = $directory.'witness.user';
            
        $inp = $directory.'matx.raw';

        $out = $directory.'matx.signed';
        
        $str = $this->run([
            'conway', 'transaction', 
            'assemble',
            '--tx-body-file', $inp,
            '--witness-file', $wt1,
            '--witness-file', $wt2,            
            '--out-file', $out
        ]);        
    }

    public function assembleRemoteSignature($witness, $strip = 0): void
    {   
        $directory = $this->txPrefix;

        $wt3 = $directory.'witness.user';
    
        file_put_contents($wt3, '{' . PHP_EOL);

        file_put_contents($wt3, '    "type": "TxWitness ConwayEra",' . PHP_EOL, FILE_APPEND);

        file_put_contents($wt3, '    "description": "Key Witness ShelleyEra",' . PHP_EOL, FILE_APPEND);
        
        file_put_contents($wt3, '    "cborHex": "' .substr($witness, $strip). '"' . PHP_EOL, FILE_APPEND);

        file_put_contents($wt3, '}' . PHP_EOL, FILE_APPEND);
      
        $wt1 = $directory.'witness.provider';

        $wt2 = $directory.'witness.user';
        
        $inp = $directory.'matx.raw';

        $out = $directory.'matx.signed';
        
        $str = $this->run([
            'conway', 'transaction', 
            'assemble',
            '--tx-body-file', $inp,
            '--witness-file', $wt1,
            '--witness-file', $wt2,            
            '--out-file', $out
        ]);        
    }

    public function buildHydraTransaction(array $txins, array $txout, string $outFile, int $fee, int $sourceLovelace = 0, string $metaData = '', string $mintingScript = ''): ?string
    {
        $txinsString = implode(' ', $txins);
        $txoutString = implode(' ', $txout);

        $this->sourceLovelace = $sourceLovelace;

        if (!empty($mintingScript)) {
            file_put_contents($this->txPrefix.'policy.script', $mintingScript);
        }

        if (!empty($metaData)) {
            file_put_contents($this->txPrefix.'metadata.json', $metaData);
        }

        if (strlen($txinsString) && strlen($changeAddress)) {
        
            if (strlen($mintingScript) && strlen($metaData)) {
                $str = $this->run([
                    'conway', 'transaction',                    
                    'build',  'raw', '--mainnet', 
                    '--socket-path', $this->socketPath,
                    '--witness-override',  '2',
                    $txinsString,
                    $txoutString,                    
                    '--minting-script-file',  $this->txPrefix.'policy.script',
                    '--metadata-json-file',  $this->txPrefix.'metadata.json',
                    '--invalid-hereafter', $this->getSlotNumber(),
                    '--fee', $fee,
                    '--out-file', $this->txPrefix.$outFile
                ]);

            } else if (strlen($mintingScript) && !strlen($metaData)) {
                $str = $this->run([
                    'conway', 'transaction',
                    'build',  'raw', '--mainnet',
                    '--socket-path', $this->socketPath,
                    '--witness-override',  '2',
                    $txinsString,
                    $txoutString,
                    '--minting-script-file',  $this->txPrefix.'policy.script',
                    '--invalid-hereafter', $this->getSlotNumber(),
                    '--fee', $fee,
                    '--out-file', $this->txPrefix.$outFile
                ]);

            } else if (!strlen($mintingScript) && strlen($metaData)) {
                $str = $this->run([
                    'conway', 'transaction',
                    'build',  'raw', '--mainnet',
                    '--socket-path', $this->socketPath,
                    '--witness-override',  '2',
                    $txinsString,
                    $txoutString,
                    '--metadata-json-file',  $this->txPrefix.'metadata.json',
                    '--invalid-hereafter', $this->getSlotNumber(),
                    '--fee', $fee,
                    '--out-file', $this->txPrefix.$outFile
                ]);

            } else {
                $str = $this->run([
                    'conway', 'transaction', 
                    'build',  'raw', '--mainnet',
                    '--socket-path', $this->socketPath,
                   '--witness-override',  '2',
                    $txinsString,
                    $txoutString,
                    '--invalid-hereafter', $this->getSlotNumber(),
                    '--fee', $fee,
                    '--out-file', $this->txPrefix.$outFile
                ]);
            }
        
            if (str_contains($str, 'Estimated') && preg_match('/(\d+)/', $str, $matches)) {
                $this->transactionFee = (int)$matches[1];
                return $matches[1];
            }
        }
        
        return null;
    }

}
