# Presta2Doli : Module de synchronisation entre Dolibarr et Prestashop
Ce module permet d'importer les produits de Prestashop dans le CRM Dolibarr afin de garder les données synchronisés entre les 2 plateformes. Prise en charge de produits simples, multilingues, déclinaisons et multiprix.

Module crée pour l'entreprise MAC2 dans le cadre d'un stage en PHP. Ce module s'installe sur Dolibarr :

1- Télécharger le module
2- Décompresser l'archive
3- Copié le dossier Mac2Sync dans le répertoir Custom de Dolibarr
4- Activer le module dans Dolibarr : Paramètres->Modules->Mac2Sync

Ce module requière une installation fonctionnelle de Prestashop et l'activation de son API nommée Webservices. Les données de cette API dont sa clé privée devront être renseignées sur la page de configuration du module Mac2Sync dans Dolibarr.
