<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Chaînes françaises.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apiurl'] = 'Adresse de MEMOVIA';
$string['apiurl_desc'] = 'Doit être l’adresse de l’outil externe MEMOVIA enregistré sur ce site (LTI 1.3).';
$string['banner_noclass'] = 'MEMOVIA : ouvrez une fois l’activité MEMOVIA de ce cours pour lancer l’adaptation de ses fichiers.';
$string['banner_ok'] = 'MEMOVIA activé · {$a} supports adaptés pour la classe';
$string['banner_twotools'] = 'MEMOVIA : ce cours contient les activités de deux outils MEMOVIA (deux établissements). Rien n’est envoyé tant qu’il en reste deux : gardez seulement celui de votre établissement, ou restreignez chaque outil à la catégorie de son établissement.';
$string['enabled'] = 'Envoyer les fichiers de cours à MEMOVIA';
$string['enabled_desc'] = 'Toutes les 5 minutes, les fichiers PDF, DOCX et PPTX visibles de tous dans les cours qui contiennent une activité MEMOVIA sont envoyés à MEMOVIA, adaptés et partagés à la classe. Un fichier caché ou à accès restreint n’est jamais envoyé. Décocher pour arrêter (fin de contrat).';
$string['err_api'] = 'MEMOVIA est injoignable. Réessaie dans une minute.';
$string['err_noactivity'] = 'Ce cours n’a pas d’activité MEMOVIA visible. Demande à ton formateur.';
$string['err_notready'] = 'Ce fichier n’est pas encore adapté par MEMOVIA. Reviens dans quelques minutes.';
$string['error_DAILY_BUDGET'] = 'limite du jour atteinte, nouvel essai automatique';
$string['error_DOCINTEL_REJECTED'] = 'fichier illisible (protégé ou abîmé)';
$string['error_EMPTY'] = 'aucun texte lisible (image scannée ?)';
$string['error_MIME'] = 'type de fichier non pris en charge';
$string['error_TOO_LARGE'] = 'fichier de plus de 18 Mo';
$string['error_TOO_MANY_ATTEMPTS'] = 'l’adaptation a échoué plusieurs fois';
$string['error_TOO_MANY_PAGES'] = 'plus de 300 pages';
$string['error_other'] = 'l’adaptation a échoué';
$string['inprogress'] = 'En cours d’adaptation';
$string['memovia:open'] = 'Ouvrir un fichier du cours avec MEMOVIA';
$string['messageprovider:newcontent'] = 'Nouveaux contenus MEMOVIA de la classe';
$string['notadapted'] = 'Non adapté';
$string['notify_intro'] = 'Ton formateur a ajouté du nouveau dans MEMOVIA pour le cours « {$a} » :';
$string['notify_note'] = '{$a}';
$string['notify_published'] = '{$a} (contenu publié par ton formateur)';
$string['notify_subject'] = 'Nouveau dans MEMOVIA : {$a}';
$string['open'] = 'Ouvrir avec MEMOVIA';
$string['open_confirm'] = 'Ouvrir ce contenu dans MEMOVIA ?';
$string['open_title'] = 'Ouvrir ce fichier dans MEMOVIA, adapté pour la lecture (nouvel onglet)';
$string['pluginname'] = 'MEMOVIA';
$string['privacy:metadata:azure'] = 'MEMOVIA lit le texte des fichiers de cours avec Microsoft Azure Document Intelligence (région UE), son sous-traitant.';
$string['privacy:metadata:azure:files'] = 'Contenu des fichiers de cours, pour en extraire le texte.';
$string['privacy:metadata:memovia'] = 'Les fichiers de cours visibles de tous, dans les cours qui ont une activité MEMOVIA, sont envoyés à MEMOVIA (UE) pour être adaptés pour la classe. Quand une personne ouvre un fichier avec MEMOVIA, son identifiant Moodle est envoyé pour ouvrir le bon fichier par le lancement LTI.';
$string['privacy:metadata:memovia:files'] = 'Fichiers de cours (nom et contenu) visibles de tous.';
$string['privacy:metadata:memovia:userid'] = 'Identifiant Moodle de la personne qui ouvre un fichier avec MEMOVIA.';
$string['task_sync_files'] = 'Envoyer les fichiers de cours à MEMOVIA';
