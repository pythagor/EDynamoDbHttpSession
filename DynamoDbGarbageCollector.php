<?php

/* Amazon AWS SDK for PHP 2 phar file
 * @link https://github.com/aws/aws-sdk-php
 */
require 'aws.phar';

use Aws\DynamoDb\DynamoDbClient;
use Aws\Common\Enum\Region;
use Aws\DynamoDb\Enum\Type;
use Aws\DynamoDb\Model\BatchRequest;

class DynamoDbGarbageCollector
{
    private $config = array(
        // Your AWS Key here
        'key'    => '',
        // Your AWS Secret here
        'secret' => '',
        'region' => 'eu-west-1',
        'tableName' => 'php-sessions',
        'idColumn' => 'id',
        'dataColumn' => 'data',
        'expireColumn' => 'expire',
        'gc_batch_size' => 25,
    );
    
    private $tableName;    
    private $tableScan;    
    private $client;
    
    public function __construct()
    {
        $aws = Aws\Common\Aws::factory($this->config);
        $this->client = $aws->get("dynamodb");
    }
    
    public function garbageCollect()
    {
        $this->tableName = $this->config['tableName'];
        $expires   = (string) time();
    
        // Setup a scan table command for finding expired session items
        $this->tableScan = $this->client->getCommand('Scan', array(
            'TableName' => $this->tableName,
            'AttributesToGet' => array(
                $this->config['idColumn'],
            ),
            'ScanFilter' => array(
                'expire' => array(
                    'ComparisonOperator' => 'LT',
                    'AttributeValueList' => array(
                        array(
                            'N' => $expires
                        )
                    ),
                ),
                'lock' => array(
                    'ComparisonOperator' => 'NULL',
                )
            ),
            //Ua::OPTION => Ua::SESSION
        ));
    }    
    // Perform scan and batch delete operations as needed
    public function batchFlush()
    {
        $deleteBatch = Aws\DynamoDb\Model\BatchRequest\WriteRequestBatch::factory(
                $this->client,
                $this->config['gc_batch_size']
        );
        
        foreach ($this->client->getIterator($this->tableScan) as $item) {
            $key = array('HashKeyElement' => $item[$this->config['idColumn']]);
            $deleteBatch->add(new Aws\DynamoDb\Model\BatchRequest\DeleteRequest($key, $this->tableName));
        }
    
        // Delete any remaining items
        $deleteBatch->flush();
    }
    
    public function batchEcho()
    {
        $res = array();
        foreach ($this->client->getIterator($this->tableScan) as $item) {
            $key = array('HashKeyElement' => $item[$this->config['idColumn']]);
            $res[] = $key;
        }
        return $res;
    }
}

$collector = new DynamoDbGarbageCollector();
$collector->garbageCollect();

/* Flush expired sessions
 * Uncomment to use
 */
//$collector->batchFlush();

/* View expired sessions
 * Uncomment to use
 */
//var_dump($collector->batchEcho());
