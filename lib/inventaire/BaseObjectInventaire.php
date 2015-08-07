<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 09:14
 */

require_once("InterfaceInventaire.php");

class BaseObjectInventaire implements InterfaceInventaire {

    function get() {
        return true;
    }

    function set() {
        return true;
    }

    function Validate() {
        return true;
    }

    function Unvalidate() {
        return true;
    }

    function getPhoto() {
        return true;
    }

    function getTitle() {
        return true;
    }

    function getInventaireNumber() {
        return true;
    }
    function getHTML() {
        return true;
    }
    function getJSON() {
        return true;
    }
}