<?php

/**
 * Provides extra functions for the domain bean
 *
 * @packaged default
 * @author Henrik Farre <hf@bellcom.dk>
 **/
class Model_Domain extends RedBean_SimpleModel
{
  /**
   * Builds the fqdn from the parts of the domain
   *
   * @return string Fully Qualified Domain Name
   * @author Henrik Farre <hf@bellcom.dk>
   */
  public function getFQDN()
  {
    return ( !is_null( $this->sub ) ? $this->sub.'.' : '' ) . $this->sld.'.'.$this->tld;
  }
} // END class Model_Domain

?>
