<?php
/**
 * Created by PhpStorm.
 * User: alexy
 * Date: 2019-03-20
 * Time: 11:03
 */

require_once 'PdoGsb.php';

class ApiMethods
{
    /**
     * Constantes contenant les messages API
     * Utile pour débogguer l'Api et comprendre précisément ce qui ne va pas en cas de problème
     */
    const UNDEFINED_ERROR     = 'Erreur non définie';
    const INVALID_CREDENTIALS = 'Identifiant ou mot de passe incorrect';
    const VALID_CREDENTIALS   = 'Connexion réussie';

    private $pdo;
    private $result = ['result' => false];

    public function __construct() {
        $this->pdo = PdoGsb::getInstance();
    }


    /**
     * Méthode permettant de récupérer l'id du membre si ses identifiants sont exacts
     *
     * @param $username Le login du membre
     * @param $password Son mot de passe
     * @return array result (la réponse API au format tableau, contient l'id du membre si ses ids sont exacts)
     */
    public function login($username, $password) {
        $query = $this->pdo->prepare('SELECT id, mdp FROM membre WHERE login = :login');

        $query->bindParam(':login', $username, PDO::PARAM_STR);
        $query->execute();
        $data = $query->fetch();

        if(isset($data['mdp']) && password_verify($password, $data['mdp'])) {
            $result['result'] = true;
            $result['message'] = self::VALID_CREDENTIALS;
            $result['idmembre'] = $data['id'];
        } else {
            $result = self::INVALID_CREDENTIALS;
        }

        return $result;
    }


    public function synchronize($memberId, $expenses) {
        $expenses = json_decode($expenses);

        // {"201904":{"annee":2019,"etape":0,"km":50,"lesFraisHf":[],"mois":4,"nuitee":0,"repas":0},"201903":{"annee":2019,"etape":0,"km":20,"lesFraisHf":[{"jour":21,"montant":120.0,"motif":""}],"mois":3,"nuitee":30,"repas":0}}

        foreach($expenses as $key => $value) {
            /*
             * Vérification si la fiche de frais existe déjà ou non
             * Si elle n'existe pas on la créé
             * Si elle existe on la met à jour
             */
           $query1 = $this->pdo->prepare('SELECT COUNT(id) FROM fichefrais WHERE idmembre = :memberId');
           $query1->bindValue(':memberId', $memberId);
           $expenseReportExists = $query1->fetchColumn();

           if(!$expenseReportExists) {
               $query2 = $this->pdo->prepare(
                   'INSERT INTO fichefrais
                              SET idmembre = :memberId, 
                              mois = :mois,
                              nbjustificatifs = :nbjustificatifs,
                              montantvalide = 0,
                              datemodif = NOW(),
                              idEtat = `CR`,
                              idVehicule = 1
               ');

               $query2->bindParam(":memberId", $memberId, PDO::PARAM_STR);
               $query2->bindParam(":mois", $key['mois'], PDO::PARAM_INT);
               $query2->bindParam(":nbJustificatifs", $key['nbJustificatifs'], PDO::PARAM_INT);

               $query2->execute();
               $query2->closeCursor();

               // Insertion des frais forfait
               $fraisForfait = array(
                   array('ETP' => $key['etape']),
                   array('KM'  => $key['KM']),
                   array('NUI' => $key['nuitee']),
                   array('REP' => $key['repas'])
               );

               for($i = 0, $iMax = count($fraisForfait); $i < $iMax; $i++) {

                   $query3 = this->$this->pdo->prepare('INSERT INTO lignefraisforfait
                                                                  SET idmembre = :memberId,
                                                                  mois = :mois,
                                                                  idfraisforfait = :idFraisForfait,
                                                                  quantite = :quantite
                   ');

                   $query3->bindValue(':memberId', $memberId, PDO::PARAM_STR);
                   $query3->bindValue(':mois', $key['mois']),
                   $query3->bindValue(':idFraisForfait', $fraisForfait[$i][0]);
                   $query3->bindValue(':quantite', $fraisForfait[$i][1]);

                   $query3->execute();
                   $query3->closeCursor();
               }



               // Insertion des frais HF
               foreach($key['lesFraisHf'] as $leFraisHf) {
                   $query3 = $this->pdo->prepare('INSERT INTO lignefraisHf 
                                                            SET idmembre = :memberId,
                                                            mois :mois,
                                                            libelle = :libelle,
                                                            date = :date,
                                                            montant = :montant
                   ');

                   $date = $key['annee']. '-'. $key['mois']. '-'. $leFraisHf['jour'];

                   $query3->bindValue(':memberId', $memberId, PDO::PARAM_STR);
                   $query3->bindValue(':mois', $leFraisHf['mois'], PDO::PARAM_INT);
                   $query3->bindValue(':libelle', $leFraisHf['motif'], PDO::PARAM_INT);
                   $query3->bindValue(':date', $date, PDO::PARAM_STR);
                   $query3->bindValue(':montant', $leFraisHf['montant'], PDO::PARAM_INT);

                   $query3->execute();
               }
           }

           else {

           }
        }
    }

    /**
     * Méthode permettant de retourner une erreur générique
     * @return array
     */
    public function getUndefinedError() {
        $result['message'] = self::UNDEFINED_ERROR;

        return $result;
    }
}