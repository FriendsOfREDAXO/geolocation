<?php

# Basiskonfiguration
#
#   Auswahl der Basiskarte: for future use

$form = rex_config_form::factory( $this->name );

    $form->addFieldset( rex_i18n::msg('geolocation_config_map') );

        $field = $form->addSelectField('default_map',$value = null,['class'=>'form-control selectpicker']); // die Klasse selectpicker aktiviert den Selectpicker von Bootstrap
        $field->setLabel( rex_i18n::msg('geolocation_config_map_default') );
        $select = $field->getSelect();
        $select->addSqlOptions( 'SELECT concat(title," [id=",id,"]") as name,id FROM '.geolocation_mapset::table()->getTableName().' ORDER BY title' );

    $form->addFieldset( rex_i18n::msg('geolocation_config_proxycache') );

        $field = $form->addTextField( 'ttl', GEOLOCATION_TTL_DEF );
        $field->setLabel( rex_i18n::msg('geolocation_form_proxycache_ttl') );
        $field->setNotice( rex_i18n::msg('geolocation_form_proxycache_ttl_notice') );
        $validator = $field->getValidator();
        $validator->add( 'notEmpty', rex_i18n::msg('geolocation_form_proxycache_ttl_error'))
                  ->add( 'type', rex_i18n::msg('geolocation_form_proxycache_ttl_error'), 'integer')
                  ->add( 'min', rex_i18n::msg('geolocation_form_proxycache_ttl_error'), GEOLOCATION_TTL_MIN)
                  ->add( 'max', rex_i18n::msg('geolocation_form_proxycache_ttl_error'), GEOLOCATION_TTL_MAX);

        $field = $form->addTextField( 'maxfiles', GEOLOCATION_CFM_DEF );
        $field->setLabel( rex_i18n::msg('geolocation_form_proxycache_maxfiles') );
        $field->setNotice( rex_i18n::msg('geolocation_form_proxycache_maxfiles_notice') );
        $validator = $field->getValidator();
        $validator->add( 'notEmpty', rex_i18n::msg('geolocation_form_proxycache_maxfiles_error'))
                    ->add( 'type', rex_i18n::msg('geolocation_form_proxycache_maxfiles_error'), 'integer')
                    ->add( 'min', rex_i18n::msg('geolocation_form_proxycache_maxfiles_error'), GEOLOCATION_CFM_MIN);


$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('geolocation_config'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
