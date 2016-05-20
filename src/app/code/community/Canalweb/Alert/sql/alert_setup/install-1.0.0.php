<?php
$installer = $this;
$installer->startSetup();
$installer->run("
DROP TABLE IF EXISTS `alerte_marques`;
CREATE TABLE `alerte_marques` (
`id` int(11) NOT NULL auto_increment,
`nom` varchar(255),
PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
$installer->run("
DROP TABLE IF EXISTS `alerte_modeles`;
CREATE TABLE `alerte_modeles` (
`id` int(11) NOT NULL auto_increment,
`marque_id` int(11) NOT NULL,
`nom` varchar(255),
PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
$installer->run("
DROP TABLE IF EXISTS `alerte`;
CREATE TABLE `alerte` (
`id` int(11) NOT NULL auto_increment,
`marque` varchar(255),
`modele` varchar(255),
`email` varchar(255),
PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
$installer->endSetup();
