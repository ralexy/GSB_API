<?php
/**
 * Created by PhpStorm.
 * User: alexy
 * Date: 2019-03-20
 * Time: 10:59
 */

class PdoGsb {
    private static $server = 'mysql:host=localhost';
    private static $db = 'dbname=gsb_frais';
    private static $user = 'root';
    private static $pwd = 'root';
    private static $myPdo = null;
    private static $instance = null;

    /**
     * Constructeur privé, crée l'instance de PDO qui sera sollicitée
     * pour toutes les méthodes de la classe
     */
    private function __construct()
    {
        try {
            self::$myPdo = new PDO(
                self::$server . ';' . self::$db,
                self::$user,
                self::$pwd
            );
            self::$myPdo->query('SET CHARACTER SET utf8');
        } catch(Exception $e) {
            die('Erreur : '. $e->getMessage());
        }
    }

    /**
     * Méthode destructeur appelée dès qu'il n'y a plus de référence sur un
     * objet donné, ou dans n'importe quel ordre pendant la séquence d'arrêt.
     */
    public function __destruct()
    {
        self::$myPdo = null;
    }

    /**
     * Fonction statique qui crée l'unique instance de la classe
     * Appel : $instancePdoGsb = PdoGsb::getInstance();
     *
     * @return l'unique objet de la classe PdoGsb
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new PdoGsb();
        }

        return self::$myPdo;
    }
}