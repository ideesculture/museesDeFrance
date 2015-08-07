<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 09:11
 */

interface InterfaceInventaire {
    public function get($name);
    public function set($name, $value);

    public function Validate();
    public function Unvalidate();
}