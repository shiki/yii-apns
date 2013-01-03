<?php

namespace YAPNS;

/**
 * Forwards the log messages of ApnsPHP to Yii's logger
 *
 * @author shiki
 */
class YAPNSLogger implements \ApnsPHP_Log_Interface
{
  public function log($message)
  {
    \Yii::log($message, \CLogger::LEVEL_INFO, 'YAPNS');
  }
}

