<?php
/**
 * EDynamoDbHttpSession class file.
 *
 * @author Andrei Chugunov <admin@pythagor.com>
 * @link https://github.com/pythagor/EDynamoDbHttpSession
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package default
 * @version 0.1
 */

/**
 * Install
 * Extract the release file under protected/extensions
 *
 * In config/main.php:
 *     'session' => array(
 *         'class' => 'ext.EDynamoDbHttpSession',
 *     ),
 *
 * Example
 * 'session' => array(
 *     'class' => 'ext.EDynamoDbHttpSession',
 *     'tableName' => 'sessions',
 *     'idColumn' => 'id',
 *     'dataColumn' => 'data',
 *     'expireColumn' => 'expire',
 * ),
 *
 */
class EDynamoDbHttpSession extends CHttpSession
{

    public $dynamoDb;

    /**
     * @var string DynamoDB table name
     */
    public $tableName = 'sessions';

    /**
     * @var string id key name
     */
    public $idColumn = 'id';

    /**
     * @var string data key name
     */
    public $dataColumn="data";

    /**
     * @var string expire key name
     */
    public $expireColumn="expire";

    /**
     * Initializes the route.
     * This method is invoked after the route is created by the route manager.
     */
    public function init()
    {
        $this->dynamoDb = new A2DynamoDb;
        parent::init();
    }

    public function getData($id)
    {

        $r = $this->dynamoDb->getItem(array(
            'TableName' => $this->tableName,
            'Key' => array(
                'HashKeyElement' => array('S' => (string)$id),
            ),
        ));

        return $r['Item'];
    }

    protected function getExipireTime()
    {
        return time() + $this->getTimeout();
    }

    /**
     * Returns a value indicating whether to use custom session storage.
     * This method overrides the parent implementation and always returns true.
     * @return boolean whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return true;
    }

    /**
     * Session open handler.
     * Do not call this method directly.
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return boolean whether session is opened successfully
     */
    public function openSession($savePath, $sessionName)
    {
        return true;
    }

    /**
     * Session read handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        $row = $this->getData($id);
        return is_null($row) ? '' : $row[$this->dataColumn]['S'];
    }

    /**
     * Session write handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return boolean whether session write is successful
     */
    public function writeSession($id, $data)
    {
        $this->dynamoDb->putItem(array(
            'TableName' => $this->tableName,
            'Item' => array(
                $this->idColumn => array('S' => $id),
                $this->dataColumn => array('S' => $data),
                $this->expireColumn => array('S' => $this->getExipireTime()),
            ),
        ));

        return true; //@todo check return from put
    }

    /**
     * Session destroy handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return boolean whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        $r = $this->dynamoDb->deleteItem(array(
            'TableName' => $this->tableName,
            'Key' => array(
                'HashKeyElement' => array('S' => (string)$id),
            ),
        ));

        return true; //@todo check return from put
    }

    /**
     * Session GC (garbage collection) handler.
     * Do not call this method directly.
     * @param integer $maxLifetime The number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return boolean whether session is GCed successfully
     */
    public function gcSession($maxLifetime)
    {
        //@TODO

        return true; //@todo check return from put
    }

    /**
     * Updates the current session id with a newly generated one.
     * Please refer to {@link http://php.net/session_regenerate_id} for more details.
     * @param boolean $deleteOldSession Whether to delete the old associated session file or not.
     * @since 1.1.8
     */
    public function regenerateID($deleteOldSession = false)
    {
        $oldId = session_id();

        parent::regenerateID(false);
        $newId = session_id();

        $row = $this->getData($oldId);
        if (!is_null($row)) {
            if ($deleteOldSession) { // Delete + Put = Update
                $this->dynamoDb->deleteItem(array(
                    'TableName' => $this->tableName,
                    'Key' => array(
                        'HashKeyElement' => array('S' => (string)$oldId),
                    ),
                ));
                $this->dynamoDb->putItem(array(
                    'TableName' => $this->tableName,
                    'Item' => array(
                        $this->idColumn => array('S' => (string)$newId),
                        $this->dataColumn => $row[$this->dataColumn],
                        $this->expireColumn => $row[$this->expireColumn],
                    ),
                ));
            } else {
                $row[$this->idColumn] = array('S' => (string)$newId);
                $this->dynamoDb->putItem(array(
                    'TableName' => $this->tableName,
                    'Item' => array($row),
                ));
            }
        } else {
            $this->dynamoDb->putItem(array(
                'TableName' => $this->tableName,
                'Item' => array(
                    $this->idColumn => array('S' => $newId),
                    $this->expireColumn => array('S' => $this->getExipireTime()),
                ),
            ));



        }
    }
}
