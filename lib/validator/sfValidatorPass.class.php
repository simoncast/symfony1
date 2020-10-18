<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfValidatorPass is an identity validator. It simply returns the value unmodified. 
 *
 * @package    symfony
 * @subpackage validator
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
<<<<<<< HEAD
 * @version    SVN: $Id: sfValidatorPass.class.php 7902 2008-03-15 13:17:33Z fabien $
=======
 * @version    SVN: $Id$
>>>>>>> fos_1.5.13
 */
class sfValidatorPass extends sfValidatorBase
{
  /**
   * @see sfValidatorBase
   */
  public function clean($value)
  {
    return $this->doClean($value);
  }

  /**
   * @see sfValidatorBase
   */
  protected function doClean($value)
  {
    return $value;
  }
}
