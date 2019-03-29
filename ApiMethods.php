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
    const BAD_INFORMATIONS = 'Informations de synchronisation manquantes ou mal formatées';
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
    private $idEtat = null;

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
            $this->result['result'] = true;
            $this->result['message'] = self::VALID_CREDENTIALS;
            $this->result['idmembre'] = $data['id'];
        } else {
            $this->result['message'] = self::INVALID_CREDENTIALS;
        }

        return $this->result;
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

        if ($expenses) {
            foreach ($expenses as $expenseLine) {
                /*
                 * Création du mois sous la forme YYYYMM
                 * Ajout au tableau $expenseLine pour être utilisé aisément dans les fonctions appellées
                 */
                $expenseLine['moisAnnee'] = $expenseLine['annee'] . sprintf("%02d", $expenseLine['mois']);

                // Création de la FDF si elle n'existe pas
                $this->createNewFicheFrais($memberId, $expenseLine);

                /**
                 * Si la fiche existe déjà
                 * Que son id d'état est différent de CR pour création
                 * On ne doit pas la modifier, donc on passe à l'itération suivante
                 */
                if ($this->idEtat && $this->idEtat != 'CR') {
                    $this->idEtat = null;
                    continue;
                }

                // Insertion des frais forfait
                $this->createOrUpdateFraisForfait($memberId, $expenseLine);

                // Insertion des frais HF
                $this->createOrUpdateFraisHf($memberId, $expenseLine);

                // Réinitialisation de la variable
                $this->idEtat = null;
            }

            $result['result'] = 'true';
            $result['message'] = self::SYNCHRONIZE_OK;
        } else {
            $result['result'] = 'false';
            $result['message'] = self::BAD_INFORMATIONS;
        }
        return $result;
    }


    private function createNewFicheFrais($memberId, $expenseLine)
    {
        /**
         * Vérification si la fiche de frais existe déjà ou non
         */
        $query = $this->pdo->prepare('SELECT COUNT(idmembre) AS ficheExiste, idetat FROM fichefrais 
                                                WHERE idmembre = :memberId
                                                AND mois = :mois
        ');
        $query->bindValue(':memberId', $memberId);
        $query->bindParam(":mois", $expenseLine['moisAnnee'], PDO::PARAM_INT);
        $query->execute();

        $data = $query->fetchAll()[0];

        $this->idEtat = $data['idetat'];
        $this->createRecord = $data['ficheExiste'];

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
            /**
             * Vérification si les frais forfait existent déjà ou non
             */
            $query = $this->pdo->prepare('SELECT COUNT(idmembre) FROM lignefraisforfait 
                                                    WHERE idmembre = :memberId
                                                    AND mois = :mois
                                                    AND idfraisforfait = :idFraisForfait
            ');
            $query->bindValue(':memberId', $memberId);
            $query->bindParam(":mois", $expenseLine['moisAnnee'], PDO::PARAM_INT);
            $query->bindParam(":idFraisForfait", $key, PDO::PARAM_STR);
            $query->execute();

            $lineExists = $query->fetchColumn();

            /**
             * Si l'enregistrement n'existe pas on l'insère, sinon on le met à jour
             */
            if (!$lineExists) {
                $query2 = $this->pdo->prepare('INSERT INTO lignefraisforfait
                                                         SET idmembre = :memberId,
                                                         mois = :mois,
                                                         idfraisforfait = :idFraisForfait,
                                                         quantite = :quantite
                ');
                $query2->bindValue(':memberId', $memberId, PDO::PARAM_STR);
                $query2->bindValue(':mois', $expenseLine['moisAnnee']);
                $query2->bindValue(':idFraisForfait', $key);
                $query2->bindValue(':quantite', $value);

                $query2->execute();
                $query2->closeCursor();
            } else {
                $query2 = $this->pdo->prepare('UPDATE lignefraisforfait
                                                         SET quantite = :quantite
                                                         WHERE idmembre = :memberId
                                                         AND mois = :mois 
                                                         AND idfraisforfait = :idFraisForfait
                ');
                $query2->bindValue(':quantite', $value);
                $query2->bindValue(':memberId', $memberId, PDO::PARAM_STR);
                $query2->bindValue(':mois', $expenseLine['moisAnnee']);
                $query2->bindValue(':idFraisForfait', $key);

                $query2->execute();
                $query2->closeCursor();
            }
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
            if (empty($leFraisHf['date'])) {
                $leFraisHf['date'] = $expenseLine['annee'] . '-' . sprintf("%02d", $expenseLine['mois']) . '-' . $leFraisHf['jour'];
            }

            /**
             * Recherche du frais HF par date de déclaration & libellé
             * Un visiteur médical ne pourra ainsi pas déclarer 2x le même frais
             * Pour le même jour quel que soit le mois en cours (évite la fraude)
             */
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
                                                         WHERE idetat = `CR`
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