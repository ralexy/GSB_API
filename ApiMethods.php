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
    const UNDEFINED_ERROR = 'Erreur non définie';
    const INVALID_CREDENTIALS = 'Identifiant ou mot de passe incorrect';
    const VALID_CREDENTIALS = 'Connexion réussie';
    const SYNCHRONIZE_OK = 'Synchronisation réussie';

    /**
     * Constantes permettant de déterminer si on créé ou non une FDF
     * et des frais HF
     */
    private $createRecord;
    private $createRecordHf;
    private $pdo;
    private $result = ['result' => false];

    public function __construct()
    {
        $this->pdo = PdoGsb::getInstance();
    }


    /**
     * Méthode permettant de récupérer l'id du membre si ses identifiants sont exacts
     *
     * @param $username Le login du membre
     * @param $password Son mot de passe
     * @return array result (la réponse API au format tableau, contient l'id du membre si ses ids sont exacts)
     */
    public function login($username, $password)
    {
        $query = $this->pdo->prepare('SELECT id, mdp FROM membre WHERE login = :login');

        $query->bindParam(':login', $username, PDO::PARAM_STR);
        $query->execute();
        $data = $query->fetch();

        if (isset($data['mdp']) && password_verify($password, $data['mdp'])) {
            $result['result'] = true;
            $result['message'] = self::VALID_CREDENTIALS;
            $result['idmembre'] = $data['id'];
        } else {
            $result = self::INVALID_CREDENTIALS;
        }

        return $result;
    }

    /**
     * Méthode appellée par l'API qui synchronise les données transmises par l'APP à la base de données
     *
     * @param $memberId
     * @param $expenses
     */
    public function synchronize($memberId, $expenses)
    {
        $expenses = json_decode($expenses, true);

        // {"201904":{"annee":2019,"etape":0,"km":50,"lesFraisHf":[],"mois":4,"nuitee":0,"repas":0},"201903":{"annee":2019,"etape":0,"km":20,"lesFraisHf":[{"jour":21,"montant":120.0,"motif":""}],"mois":3,"nuitee":30,"repas":0}}

        foreach ($expenses as $expenseLine) {
            /*
             * Création du mois sous la forme YYYYMM
             * Ajout au tableau $expenseLine pour être utilisé aisément dans les fonctions appellées
             */
            $expenseLine['moisAnnee'] = $expenseLine['annee'] . sprintf("%02d", $expenseLine['mois']);

            // Création de la FDF si elle n'existe pas
            $this->createNewFicheFrais($memberId, $expenseLine);

            // Insertion des frais forfait
            $this->createOrUpdateFraisForfait($memberId, $expenseLine);

            // Insertion des frais HF
            $this->createOrUpdateFraisHf($memberId, $expenseLine);
        }

        $result['message'] = self::SYNCHRONIZE_OK;

        return $result;
    }


    private function createNewFicheFrais($memberId, $expenseLine)
    {
        /**
         * Vérification si la fiche de frais existe déjà ou non
         */
        $query = $this->pdo->prepare('SELECT COUNT(idmembre) FROM fichefrais 
                                                WHERE idmembre = :memberId
                                                AND mois = :mois
        ');
        $query->bindValue(':memberId', $memberId);
        $query->bindParam(":mois", $expenseLine['moisAnnee'], PDO::PARAM_INT);
        $query->execute();

        $this->createRecord = $query->fetchColumn();

        /**
         * Si la fiche de frais n'existe pas on la créé dans la table fichefrais
         */
        if (!$this->createRecord) {
            $query2 = $this->pdo->prepare('INSERT INTO fichefrais
                                                        SET idmembre = :memberId, 
                                                        mois = :mois,
                                                        nbjustificatifs = 0,
                                                        montantvalide = 0,
                                                        datemodif = NOW(),
                                                        idEtat = \'CR\',
                                                        idVehicule = 1
            ');

            $query2->bindParam(":memberId", $memberId, PDO::PARAM_STR);
            $query2->bindParam(":mois", $expenseLine['moisAnnee'], PDO::PARAM_INT);

            $query2->execute();
            $query2->closeCursor();
        }

        $query->closeCursor();
    }


    private function createOrUpdateFraisForfait($memberId, $expenseLine)
    {
        $fraisForfait = array(
            'ETP' => $expenseLine['etape'],
            'KM' => $expenseLine['km'],
            'NUI' => $expenseLine['nuitee'],
            'REP' => $expenseLine['repas']
        );

        foreach ($fraisForfait as $key => $value) {
            $query3 = $this->pdo->prepare('INSERT INTO lignefraisforfait
                                                                 SET idmembre = :memberId,
                                                                 mois = :mois,
                                                                 idfraisforfait = :idFraisForfait,
                                                                 quantite = :quantite
                   ');
            $query3->bindValue(':memberId', $memberId, PDO::PARAM_STR);
            $query3->bindValue(':mois', $expenseLine['moisAnnee']);
            $query3->bindValue(':idFraisForfait', $key);
            $query3->bindValue(':quantite', $value);

            $query3->execute();
            $query3->closeCursor();
        }
    }

    /**
     * Méthode permettant de créer un frais HF ou de le mettre à jour si il existe déjà
     *
     * @param $memberId
     * @param $expenseLine
     *
     * @return array
     */
    private function createOrUpdateFraisHf($memberId, $expenseLine)
    {
        foreach ($expenseLine['lesFraisHf'] as $leFraisHf) {
            /*
             * Ajout de la date au tableau sous le format AAAA-MM-JJ
             */
            if(empty($leFraisHf['date'])) {
                $leFraisHf['date'] = $expenseLine['annee'] . '-' . sprintf("%02d", $expenseLine['mois']) . '-' . $leFraisHf['jour'];
            }

            $query = $this->pdo->prepare('SELECT COUNT(id) FROM lignefraishorsforfait 
                                                        WHERE idmembre = :memberId
                                                        AND date = :date
                                                        AND libelle = :libelle
            ');

            $query->bindValue(':memberId', $memberId, PDO::PARAM_INT);
            $query->bindValue(':date', $leFraisHf['date'], PDO::PARAM_STR);
            $query->bindValue(':libelle', $leFraisHf['motif'], PDO::PARAM_STR);
            $query->execute();

            $this->createRecordHf = $query->fetchColumn();

            if (!$this->createRecordHf) {
                $query2 = $this->pdo->prepare('INSERT INTO lignefraishorsforfait 
                                                         SET idmembre = :memberId,
                                                         mois = :mois,
                                                         libelle = :libelle,
                                                         date = :date,
                                                         montant = :montant
                       ');

                $query2->bindValue(':memberId', $memberId, PDO::PARAM_STR);
                $query2->bindValue(':mois', $expenseLine['moisAnnee'], PDO::PARAM_INT);
                $query2->bindValue(':libelle', $leFraisHf['motif'], PDO::PARAM_STR);
                $query2->bindValue(':date', $leFraisHf['date'], PDO::PARAM_STR);
                $query2->bindValue(':montant', $leFraisHf['montant'], PDO::PARAM_INT);

                $query2->execute();
            } else {
                $query2 = $this->pdo->prepare('UPDATE lignefraishorsforfait 
                                                         SET idmembre = :memberId,
                                                         mois = :mois,
                                                         libelle = :libelle,
                                                         date = :date,
                                                         montant = :montant
                ');

                $query2->bindValue(':libelle', $leFraisHf['motif'], PDO::PARAM_STR);
                $query2->bindValue(':montant', $leFraisHf['montant'], PDO::PARAM_INT);
                $query2->bindValue(':date', $leFraisHf['date'], PDO::PARAM_STR);
                $query2->bindValue(':memberId', $memberId, PDO::PARAM_STR);
                $query2->bindValue(':mois', $expenseLine['moisAnnee'], PDO::PARAM_INT);

                $query2->execute();
            }

            $query->closeCursor();
            $query2->closeCursor();
        }
    }

    /**
     * Méthode permettant de retourner une erreur générique
     *
     * @return array
     */
    public function getUndefinedError()
    {
        $result['message'] = self::UNDEFINED_ERROR;

        return $result;
    }
}