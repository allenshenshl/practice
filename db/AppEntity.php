<?php

namespace app\framework\db;

use app\framework\db\EntityBase;

/**
 * 微网站,具体app所使用的实体
 * Class AppEntity
 */
abstract class AppEntity extends EntityBase
{

    /**
     * @return Connection
     * @throws \yii\base\InvalidConfigException
     */
    public static $connectionPool = [];

    public static function getDb()
    {
        $tenantCode = self::getTenantCode();
        if (array_key_exists($tenantCode, self::$connectionPool) && self::$connectionPool[$tenantCode]) {
            return self::$connectionPool[$tenantCode];
        }
        
        $db = static::getDbRoutingInstance()->getCurrentTenantDbConnection();
        self::$connectionPool[$tenantCode] = $db;
        
        return $db;
    }

    /**
     * 获取租户码
     * @return string
     */
    public static function getTenantCode()
    {
        $tenantReader = \Yii::$container->get('app\framework\biz\tenant\TenantReaderInterface');
        return $tenantReader->getCurrentTenantCode();
    }
}
