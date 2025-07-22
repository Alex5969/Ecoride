# Guide d'Installation et de Configuration Locale "Ecoride"

Ce document vous fournit des instructions pas à pas pour configurer votre environnement de développement local pour l'application "Ecoride". Il couvre l'installation de WampServer (Apache, PHP, MySQL), MongoDB, Composer, et le déploiement de votre code.

En cas de problème, n'hésitez pas à consulter la **Section 10. Dépannage Courant** à la fin de ce guide.

---

## 1. Prérequis

**Système d'exploitation :** Windows 7 ou supérieur (64 bits recommandé)  
**Mémoire vive (RAM) :** 1 Go minimum (4 Go+ recommandé)  
**Espace disque :** Suffisant pour les installations et votre projet  
**Visual C++ Redistributables :** Installez toutes les versions x86 et x64  
🔗 [Lien vers les redistribuables Visual C++](https://support.microsoft.com/fr-fr/topic/derni%C3%A8res-versions-prises-en-charge-de-redistributables-visual-c-pour-visual-studio-2647da03-1eea-4433-9fa3-a5382578538d)

**Conflits de ports :**
- Apache : 80
- MySQL : 3306
- MongoDB : 27017

---

## 2. Installation de WampServer

**Téléchargement :** [wampserver.com/download/](http://www.wampserver.com/download/)

**Installation :**
- Lancez l’installateur en tant qu’administrateur
- Répertoire : `C:\wamp64`
- Composants : par défaut

**Vérification :**
- L’icône WampServer doit devenir **verte**

---

## 3. Installation de MongoDB (Serveur)

**Préparation des dossiers :**
- `C:\data`
- `C:\data\db`
- `C:\data\log`

**Téléchargement :** [mongodb.com/try/download/community](https://www.mongodb.com/try/download/community)

**Installation :**
- Option **"Complete"**
- "Install MongoDB as a Service" activée
- Dossiers de données/logs : `C:\data\db` et `C:\data\log`

**Vérification :**
- `services.msc` > `MongoDB Server (MongoDB)` doit être **En cours d’exécution**

---

## 4. Configuration PHP pour MongoDB (Pilote)

**Accédez à** : `http://localhost/phpinfo.php`  
- PHP Version : 8.3.14  
- Thread Safety : TS  
- Architecture : x64

**Téléchargement du pilote MongoDB :**  
🔗 [https://pecl.php.net/package/mongodb](https://pecl.php.net/package/mongodb)  
Fichier requis : `php_mongodb-2.1.0-8.3-ts-vs16-x64.zip`

**Installation :**
- Extraire et copier `php_mongodb.dll` dans `C:\wamp64\bin\php\php8.3.14\ext\`
- Modifier `php.ini` :
  ```ini
  extension=php_mongodb.dll
  ```

**Redémarrage de WampServer**  
- Redémarrer tous les services

**Vérification :**
- Via `phpinfo()` > Section `mongodb`

---

## 5. Installation de Composer et de la Bibliothèque PHP MongoDB

**Composer :**
- 🔗 [getcomposer.org/download](https://getcomposer.org/download/)
- Vérifiez avec : `composer --version`

**Installation bibliothèque MongoDB :**
```bash
cd C:\wamp64\www\ecoride
composer require mongodb/mongodb
```

**Dans vos fichiers PHP MongoDB :**
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
// ...
?>
```


## 6. Configuration du Pare-feu (Exceptions)

**Ajouter mongod.exe :**
- Pare-feu Windows > Règles de trafic entrant > Nouvelle règle > Programme
- Chemin : `C:\Program Files\MongoDB\Server\X.Y\bin\mongod.exe`

**Ajouter httpd.exe :**
- Chemin : `C:\wamp64\bin\apache\apache2.4.62\bin\httpd.exe`

---

## 7. Gestion des Bases de Données (MySQL & MongoDB)

### 7.1. Base de Données MySQL

**Création via console :**
```bash
mysql -u root
CREATE DATABASE ecoride_mysql_db;
EXIT;
```

**Importation du fichier SQL :**
```bash
mysql -u root ecoride_mysql_db < C:\wamp64\www\ecoride\database.sql
```

### 7.2. Base de Données MongoDB

**Création automatique :**
- `ecoride_analytics` est créée lors de la 1re insertion de données via l’app PHP.

---

## 8. Déploiement du Code "Ecoride"

Copiez vos fichiers dans `C:\wamp64\www\ecoride\`

---

## 9. Lancement et Accès à l'Application

**Démarrage de WampServer :**
- L’icône doit être verte

**Accès navigateur :**
```
http://localhost/ecoride/
```
ou
```
http://localhost/ecoride/index.php
```

---

## 10. Dépannage Courant

### Icône WampServer orange/rouge :
- Conflit de port 80 → Modifier `httpd.conf` :
  ```
  Listen 8080
  ```
- Conflit de port 3306 → Modifier `my.ini`

### Erreurs PHP MongoDB :
- Assurez-vous que :
  - DLL correcte pour PHP 8.3.14 TS x64
  - Ligne `extension=php_mongodb.dll` présente
  - Fichier dans le bon dossier `ext/`
  - Redémarrage Wamp effectué

### Erreurs de classe MongoDB :
- Exécuter :
  ```bash
  composer require mongodb/mongodb
  ```
- Ajouter `require_once __DIR__ . '/../vendor/autoload.php';`

### "Unsupported wire version"
- Incompatibilité entre pilote et serveur → vérifier compatibilité et mettre à jour

### Connexion refusée (27017)
- Service MongoDB non démarré
- Port en conflit → `netstat -an | findstr "27017"`
- Pare-feu bloque → ajouter une règle entrante

### Page blanche / Erreur 404
- Vérifiez l’arborescence du projet
- Vérifiez les logs Apache
