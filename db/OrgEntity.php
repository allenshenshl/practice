<?php
namespace app\framework\db;

use Yii;
use yii\db\Connection;
use yii\db\StaleObjectException;

use app\framework\utils\DateTimeHelper;


/**
 * 租户的后台管理中心所使用的实体
 * Class OrgEntity
 */
abstract class OrgEntity extends EntityBase
{
    /**
     * 0:分公司库, 1:租户主公司库
     * @var float
     */
    public static $level = 0;

    /**
     * @param string $org_id
     * @return Connection
     */
    private static function _getDb_dsn($org_id='')
    {
        if(empty($org_id))
        {
            if(static::$level == 1){
                return static::getDbRoutingInstance()->getCurrentTenantDbConnection();
            }
            return static::getDbRoutingInstance()->getCurrentDbConnection();
        }
        else
        {
            return static::getDbRoutingInstance()->getDbConnection($org_id);
        }
    }

    /**
     * @param string $org_id 组织id, 定向指定的分公司库
     * @return boolean whether the attributes are valid and the record is inserted successfully.
     * @throws \Exception in case insert failed.
     */
    public function insertTo($org_id)
    {
        if(empty($org_id)){
            throw new \InvalidArgumentException('$org_id');
        }

        if (!$this->beforeSave(true)) {
            return false;
        }

        $db = static::_getDb_dsn($org_id);
        $result = false;

        if ($this->isTransactional(self::OP_INSERT)) {
            $transaction = $db->beginTransaction();
            try {
                $attrs = $this->getAttributes();
                $command = $db->createCommand()->insert($this->tableName(), $attrs);
                if (!$command->execute()) {
                    $transaction->rollBack();

                    return false;
                }

                $this->afterSave(true, $attrs);
                $transaction->commit();
                $result = true;

            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
        else
        {
            $attrs = $this->getAttributes();
            $command = $db->createCommand()->insert($this->tableName(), $attrs);
            if (!$command->execute()) {
                return false;
            }
            $this->afterSave(true, $attrs);
            $result = true;
        }

        return $result;
    }

    /**
     * Saves the current record.
     * @param string $org_id 组织id, 定向指定的分公司库
     * @return boolean whether the saving succeeds
     */
    public function saveTo($org_id)
    {
        if(empty($org_id)){
            throw new \InvalidArgumentException('$org_id');
        }

        $attributeNames = null;
        if ($this->getIsNewRecord()) {
            return $this->insertTo($org_id);
        } else {
            return $this->updateTo($org_id) !== false;
        }
    }

    /**
     * @param string $org_id 组织id, 定向指定的分公司库
     * @return integer|boolean the number of rows affected, or false if validation fails
     * or [[beforeSave()]] stops the updating process.
     * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
     * being updated is outdated.
     * @throws \Exception in case update failed.
     */
    public function updateTo($org_id)
    {
        if (empty($org_id)) {
            throw new \InvalidArgumentException('$org_id');
        }

        if (!$this->beforeSave(false)) {
            return false;
        }
        $values = $this->getAttributes();//$this->getDirtyAttributes();
        if (empty($values)) {
            $this->afterSave(false, $values);
            return 0;
        }

        $keys = $this->primaryKey();
        $condition = [];
        if (count($keys) === 1 && !$values) {
            $condition = isset($values[$keys[0]]) ? $values[$keys[0]] : null;
        } else {
            $condition = [];
            foreach ($keys as $name) {
                $condition[$name] = isset($values[$name]) ? $values[$name] : null;
            }
        }

        $lock = $this->optimisticLock();
        if ($lock !== null) {
            $values[$lock] = $this->$lock + 1;
            $condition[$lock] = $this->$lock;
        }
        // We do not check the return value of updateAll() because it's possible
        // that the UPDATE statement doesn't change anything and thus returns 0.
        $rows = $this->updateAllTo($values, $condition, $org_id);

        if ($lock !== null && !$rows) {
            throw new StaleObjectException('The object being updated is outdated.');
        }

        $this->afterSave(false, $values);

        return $rows;
    }

    /**
     * @param array $attributes attribute values (name-value pairs) to be saved into the table
     * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to [[Query::where()]] on how to specify this parameter.
     * @param string $org_id 组织id, 定向指定的分公司库
     * @return integer the number of rows updated
     */
    public static function updateAllTo($attributes, $condition, $org_id)
    {
        if(empty($org_id)){
            throw new \InvalidArgumentException('$org_id');
        }

        $db = static::_getDb_dsn($org_id);
        $command = $db->createCommand();
        $params = [];
        $command->update(static::tableName(), $attributes, $condition, $params);
        return $command->execute();
    }

    /**
     * @param string|array $condition the conditions that will be put in the WHERE part of the DELETE SQL.
     * Please refer to [[Query::where()]] on how to specify this parameter.
     * @param string $org_id 组织id, 定向指定的分公司库
     * @return integer the number of rows deleted
     */
    public static function deleteAllTo($condition, $org_id)
    {
        if(empty($org_id)){
            throw new \InvalidArgumentException('$org_id');
        }

        $params = [];
        $db = static::_getDb_dsn($org_id);
        $command = $db->createCommand();
        $command->delete(static::tableName(), $condition, $params);

        return $command->execute();
    }

    public static function getDb($org_id = '')
    {
        return static::_getDb_dsn($org_id);
    }

    protected function getAutoInsertingColumns()
    {
        $now = DateTimeHelper::now();

        $sessionAccessor = Yii::$container->get('app\framework\auth\interfaces\UserSessionAccessorInterface');
        if(!isset($sessionAccessor)){
            throw new \Exception('请注入app\framework\auth\interfaces\UserSessionAccessorInterface实例');
        }

        $session = $sessionAccessor->getUserSession();
        $arrs = ['created_on'=>$now, 'modified_on'=>$now, 'is_deleted'=>0];

        if(isset($session)){

           $user_id = $session->user_id;
           $arrs['created_by'] = $user_id;
           $arrs['modified_by'] = $user_id;
        }

        return $arrs;

    }

    protected function getAutoUpdatingColumns()
    {
        $now = DateTimeHelper::now();

        $sessionAccessor = Yii::$container->get('app\framework\auth\interfaces\UserSessionAccessorInterface');
        if(!isset($sessionAccessor)){
            throw new \Exception('请注入app\framework\auth\interfaces\UserSessionAccessorInterface实例');
        }

        $session = $sessionAccessor->getUserSession();
        $arrs = ['modified_on'=>$now];

        if(isset($session)){

           $user_id = $session->user_id;
           $arrs['modified_by'] = $user_id;
        }

        return $arrs;

    }

}

