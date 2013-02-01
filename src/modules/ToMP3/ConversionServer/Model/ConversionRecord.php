<?php

namespace ToMP3\ConversionServer\Model;

class ConversionRecord extends \ymF\Model\ModelBase
{
  protected $table = 'ConversionLog';

  protected $data = array(
    'id'                    => null,
    'conversionDescriptor'  => '',
    'siteId'                => '',
    'videoId'               => '',
    'quality'               => '',
    'format'                => '',
    'conversions'           => 0, // Conversion requests
    'downloads'             => 0, // Downloads
    'lastConversionTime'    => 0,
    'lastDownloadTime'      => 0,
  );
}