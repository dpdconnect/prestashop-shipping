CREATE TABLE IF NOT EXISTS `_PREFIX_dpdshipment_label` (
  `id_dpdcarrier_label` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mps_id` varchar(255) NOT NULL,
  `label_nummer` text NOT NULL,
  `order_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `shipped` tinyint(4) NOT NULL,
  `label` mediumblob NOT NULL,
  `retour` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_dpdcarrier_label`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `_PREFIX_parcelshop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `parcelshop_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `_PREFIX_dpd_product_attributes` (
  `id_dpd_product_attributes` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `hs_code` varchar(255) NOT NULL,
  `country_of_origin` varchar(255) NOT NULL,
  `customs_value` int NOT NULL,
  `age_check` varchar(255) NOT NULL,
  PRIMARY KEY (`id_dpd_product_attributes`),
  UNIQUE (product_id)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `_PREFIX_dpd_batches` (
  `id_dpd_batches` mediumint(9) NOT NULL AUTO_INCREMENT,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  `shipment_count` smallint(5) NOT NULL,
  `success_count` smallint(5) DEFAULT 0,
  `failure_count` smallint(5) DEFAULT 0,
  `status` varchar(255) NOT NULL,
PRIMARY KEY id (id_dpd_batches),
INDEX created_at (created_at)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `_PREFIX_dpd_jobs` (
  `id_dpd_jobs` mediumint(9) NOT NULL AUTO_INCREMENT,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  `external_id` varchar(255) NOT NULL,
  `batch_id` varchar(255) NOT NULL,
  `order_id` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `error` text,
  `state_message` text,
  `label_id` int NULL,
  PRIMARY KEY id (id_dpd_jobs),
  INDEX created_at (created_at),
  INDEX batch_id (batch_id)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `_PREFIX_carrier_dpd_product` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `carrier_id` int(11) NOT NULL,
    `dpd_product_code` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
