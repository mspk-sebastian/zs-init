<?php
namespace Zend\Init\Steps;

use Zend\Log;
use Zend\State;
use Zend\Init\Result;

class LicenseStep extends AbstractStep
{
    const LICENSE_FILE="/etc/zend.lic";

    public function __construct()
    {
        parent::__construct("license setup step");
    }

    public function execute(State $state)
    {
        $state->log->log(Log::INFO, "Starting {$this->name}");
        self::zendServerControl('stop', $state->log);

        if (is_file(self::LICENSE_FILE)) {
            $state->log->log(Log::INFO, "Analyzing license file");
            $license = json_decode(file_get_contents(self::LICENSE_FILE), true);
            if ($license == null) {
                $state->log->log(Log::WARNING, "Could not parse license file, continuing");
            } else {
                $state->log->log(Log::INFO, "Loaded license");
                $state['ZEND_LICENSE_KEY'] = $license['ZEND_LICENSE_KEY'];
                $state['ZEND_LICENSE_ORDER'] = $license['ZEND_LICENSE_ORDER'];
            }

            $state->log->log(Log::INFO, "Deleting license file");
            unlink(self::LICENSE_FILE);
        }

        if (isset($state['ZEND_LICENSE_KEY'], $state['ZEND_LICENSE_ORDER'])) {
            $state->log->log(Log::INFO, "Setting license");
            self::pregReplaceFile("/zend\\.serial_number=.*$/m", "zend.serial_number={$state['ZEND_LICENSE_KEY']}", "/usr/local/zend/etc/conf.d/ZendGlobalDirectives.ini");
            self::pregReplaceFile("/zend\\.user_name=.*$/m", "zend.user_name={$state['ZEND_LICENSE_ORDER']}", "/usr/local/zend/etc/conf.d/ZendGlobalDirectives.ini");
            exec("sqlite3 /usr/local/zend/var/db/zsd.db \"UPDATE ZSD_DIRECTIVES set DISK_VALUE='{$state['ZEND_LICENSE_KEY']}' WHERE NAME='zend.serial_number'\"");
            exec("sqlite3 /usr/local/zend/var/db/zsd.db \"UPDATE ZSD_DIRECTIVES set DISK_VALUE='{$state['ZEND_LICENSE_ORDER']}' WHERE NAME='zend.user_name'\"");
            exec("sqlite3 /usr/local/zend/var/db/zsd.db \"DELETE FROM ZSD_NOTIFICATIONS WHERE TYPE=26\"");
        }

        $state->log->log(Log::INFO, "Finished {$this->name}");
        return new Result(Result::STATUS_SUCCESS);
    }
}
