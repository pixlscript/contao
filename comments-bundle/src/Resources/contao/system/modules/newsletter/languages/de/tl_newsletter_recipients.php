<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005-2009 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2009
 * @author     Leo Feyer <leo@typolight.org>
 * @package    Newsletter
 * @license    LGPL
 * @filesource
 */


/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['email']   = array('E-Mail-Adresse', 'Bitte geben Sie die E-Mail-Adresse des Abonnenten ein.');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['active']  = array('Abonnenten aktivieren', 'Abonnenten werden normalerweise automatisch aktiviert (double-opt-in).');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['source']  = array('Quelldateien', 'Bitte wählen Sie die zu importierenden CSV-Dateien aus der Dateiübersicht.');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['ip']      = array('IP-Adresse', 'Die IP-Adresse des Abonnenten.');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['addedOn'] = array('Registrierungsdatum', 'Das Datum des Abonnements.');


/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['email_legend'] = 'E-Mail-Adresse';


/**
 * Reference
 */
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['confirm']    = '%s Abonnenten wurden importiert.';
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['subscribed'] = 'registriert am %s';
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['manually']   = 'manuell hinzugefügt';


/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['new']    = array('Abonnenten hinzufügen', 'Einen neuen Abonnenten hinzufügen');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['show']   = array('Abonnentendetails', 'Details des Abonnenten ID %s anzeigen');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['edit']   = array('Abonnenten bearbeiten', 'Abonnenten ID %s bearbeiten');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['copy']   = array('Abonnenten duplizieren', 'Abonnenten ID %s duplizieren');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['delete'] = array('Abonnenten löschen', 'Abonnenten ID %s löschen');
$GLOBALS['TL_LANG']['tl_newsletter_recipients']['import'] = array('CSV-Import', 'Abonnenten aus einer CSV-Datei importieren');

?>