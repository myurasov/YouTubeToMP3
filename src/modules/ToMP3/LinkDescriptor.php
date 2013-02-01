<?php

namespace ToMP3;

use ToMP3\ConversionDescriptor;

/**
 * Link descriptor
 */
class LinkDescriptor
{
  private $conversionDescriptor;
  private $host;

  public function __construct(ConversionDescriptor $conversionDescriptor, $host)
  {
    $this->conversionDescriptor = $conversionDescriptor;
    $this->host = $host;
  }

  /**
   * Create from string
   *
   * @param string $linkDescriptor
   * @return LinkDescriptor
   */
  public static function fromString($linkDescriptor)
  {
    @list($descriptorString, $host) =
      explode('@', base64_decode($linkDescriptor));
    $conversionDescriptor = ConversionDescriptor::fromString($descriptorString);
    
    return new self($conversionDescriptor, $host);
  }

  public function __toString()
  {
    return rtrim(base64_encode((string) $this->conversionDescriptor . '@' . $this->host), '=');
  }

  /**
   * @return ConversionDescriptor
   */
  public function getConversionDescriptor()
  {
    return $this->conversionDescriptor;
  }

  public function setConversionDescriptor(ConversionDescriptor $conversionDescriptor)
  {
    $this->conversionDescriptor = $conversionDescriptor;
  }

  public function getHost()
  {
    return $this->host;
  }

  public function setHost($host)
  {
    $this->host = $host;
  }
}