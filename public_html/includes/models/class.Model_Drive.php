<?php
/**
 * Provides functions related to the harddrive bean
 *
 * @packaged default
 * @author Henrik Farre <hf@bellcom.dk>
 **/
class Model_Harddrive extends RedBean_SimpleModel
{
  /**
   * Tries to set the brand value to something usefull by looking at the model value
   *
   * @return void
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  public function setBrand($model)
  {
    $found = false;
    if ( !$found && substr($model, 0, 2) == 'ST')
    {
      $brand = 'Seagate';
      $found = true;
    }
    if ( !$found && substr($model, 0, 2) == 'IC')
    {
      $brand = 'IBM';
      $found = true;
    }
    if ( !$found && substr($model, 0, 3) == 'WDC')
    {
      $brand = 'Western Digital';
      $found = true;
    }
    if ( !$found && substr($model, 0, 7) == 'TOSHIBA')
    {
      $brand = 'Toshiba';
      $found = true;
    }
    if ( !$found && substr($model, 0, 7) == 'SAMSUNG')
    {
      $brand = 'Samsung';
      $found = true;
    }
    if ( !$found && substr($model, 0, 6) == 'MAXTOR')
    {
      $brand = 'Maxtor';
      $found = true;
    }

    $this->brand = ($found) ? $brand : 'Unknown';
  }
} // END class Model_Harddrive
