<?php

namespace app\framework\db;

use yii\db\ActiveRecord;

use app\framework\biz\db\DbRoutingInterface;
use app\framework\utils\StringHelper;

abstract class EntityBase extends ActiveRecord
{
    protected $is_uniqid = true;
    protected static $orgDbConnCollection = [];

    /**
     * @return DbRoutingInterface
     * @throws \yii\base\InvalidConfigException
     */
    protected static function getDbRoutingInstance()
    {
        return \Yii::$container->get('app\framework\biz\db\DbRoutingInterface');
    }

    protected abstract function getAutoInsertingColumns();

    protected abstract function getAutoUpdatingColumns();

    public function beforeSave($insert)
    {
        if ($this->isNewRecord) {
            if (empty($this->id) && $this->is_uniqid) {
                $this->id = StringHelper::uuid();
            }

            // 自动生成创建时间、修改时间等
            $schema = static::getTableSchema();
            $attrs = $this->getAutoInsertingColumns();
            $this->fillColumn($schema, $attrs);
        } else {
            // 自动生成创建时间、修改时间等
            $schema = static::getTableSchema();
            $attrs = $this->getAutoUpdatingColumns();
            $this->fillColumn($schema, $attrs);
        }

        return true;
    }

    private function fillColumn($schema, $attrs)
    {
        if (empty($attrs)) {
            return;
        }

        foreach ($attrs as $name => $value) {
            $col = $schema->getColumn($name);
            if (isset($col)) {
                $this->$name = $value;
            }

        }
    }

    /**
     * @param array|object $db_dsn
     * @return \yii\db\Connection
     */
    public static function toConnection($db_dsn)
    {
        if (!isset($db_dsn)) {
            throw new \InvalidArgumentException('$db_dsn');
        }

        if (!is_array($db_dsn)) {
            $db_dsn = get_object_vars($db_dsn);
        }
        $key = sha1($db_dsn['host'] . $db_dsn['dbname']);
        if (!isset(static::$orgDbConnCollection[$key])) {
            $dbConnection = [
                'dsn' => 'mysql:host=' . $db_dsn['host'] . ';dbname=' . $db_dsn['dbname'],
                'username' => $db_dsn['uid'],
                'password' => $db_dsn['pwd'],
                'charset' => 'utf8'
            ];

            //设置只读库连接
            if (isset($db_dsn['readonly_host']) && !empty($db_dsn['readonly_host'])) {
                $dbConnection['slaveConfig'] = [
                    'username' => $db_dsn['uid'],
                    'password' => $db_dsn['pwd'],
                    'charset' => 'utf8',
                    'attributes' => [
                        \PDO::ATTR_TIMEOUT => 10,
                    ],
                ];
                $dbConnection['slaves'] = [
                    ['dsn' => 'mysql:host='. $db_dsn['readonly_host'] .';dbname=' . $db_dsn['dbname']],
                ];
            }

            static::$orgDbConnCollection[$key] = new \yii\db\Connection($dbConnection);
        }

        return static::$orgDbConnCollection[$key];
    }
}
