<?php

/**
 * Forwards the log messages of ApnsPHP to Yii's logger
 *
 * @author shiki
 */
class SAPNSLogger implements ApnsPHP_Log_Interface
{
  public function log($message)
  {
    Yii::log($message, CLogger::LEVEL_INFO, 'SAPNS');
  }
}

