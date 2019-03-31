
# API GSB

Cette API est destinée au laboratoire Galaxy Swiss Bourdin. Elle permet de synchroniser les données saisies par leurs visiteurs médicaux entre leurs smartphones et la base de données centrale de GSB.

## Guide de démarrage
Ces instructions vous permettront de récupérer ce projet et de le lancer sur une machine locale de développement à des fins de tests.

### Prérequis

L'application web GSB doit préalablement être installée et opérationnelle, plus d'informations ici : https://github.com/ralexy/GSB_PHP/blob/master/README.md

Vous devez posséder un serveur web et y installer

```
PHP 7 & MySQL
```

### Installation

Il faut éditer la configuration SQL du fichier, en précisant vos identifiants **/PdoGsb.php**

La configuration locale la plus commune pour PdoGsb.php est :

```
'localhost' pour $serveur
'root' pour $user
'' OU 'root' pour $mdp
```
L'application web sera consultable via le dossier **/www/**, il est vivement conseillé de mettre en place un VirtualHost ou bien un .htaccess pour empêcher de remonter l'arborescence du serveur (particulièrement si celui-ci est accessible via Internet).

## Tester l'application

Vous pouvez tester l'application avec des comptes utilisateurs ou comptable, il vous suffira d'utiliser ces couples d'identifiants pour les visiteurs :
```
Utilisateur : cbedos
Mot de passe : gmhxd

Utilisateur : ltusseau
Mot de passe : ktp3s
```

Les deux requêtes méthodes utilisables sont login et synchronize, voici deux exemples de requêtes pouvant être passées à l'API :

L'API consultable ici : https://gsb.alexy-rousseau.com/api/

**Action pour se connecter :**
- GET | POST ?action=login&username{username}&password={password}

**Action pour synchroniser ses données :** 
- GET | POST ?action=synchronize&memberId={memberId}&expenses={"201809":{"annee":2018,"etape":0,"km":50,"lesFraisHf":[],"mois":9,"nuitee":10,"repas":0},"201803":{"annee":2018,"etape":0,"km":19,"lesFraisHf":[{"jour":21,"montant":1336.0,"motif":"Test 2"}],"mois":3,"nuitee":55,"repas":0}}

## Conçu avec

* [PHPStorm](https://www.jetbrains.com/phpstorm/) IDE spécialisé pour PHP, édité par la société JetBrains également co-autrice de AndroidStudio

## Versioning

GitHub a été utilisé pour maintenir un versionning du projet.

## Auteur(s)

* **Alexy ROUSSEAU** - Etudiant BTS SIO - <contact@alexy-rousseau.com>
