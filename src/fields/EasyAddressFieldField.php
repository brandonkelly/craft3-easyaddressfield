<?php

namespace studioespresso\easyaddressfield\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\helpers\Db;
use League\ISO3166\ISO3166;
use studioespresso\easyaddressfield\assetbundles\easyaddressfield\EasyAddressFieldAsset;
use studioespresso\easyaddressfield\Plugin;
use studioespresso\easyaddressfield\models\EasyAddressFieldModel;
use studioespresso\easyaddressfield\services\GeoLocationService;
use yii\db\Schema;


class EasyAddressFieldField extends Field implements PreviewableFieldInterface {


	public $hasContentColumn = false;

	public $geoCode = true;
	public $showCoordinates = false;
	public $defaultCountry;
	public $fields = array(
		'name'       => false,
		'street'     => true,
		'street2'    => false,
		'postalCode' => true,
		'city'       => true,
		'state'      => false,
		'country'    => true,
	);



	public static function displayName(): string {
		return Craft::t( 'easyaddressfield', 'Easy Address Field' );
	}

	/**
	 * @return string
	 * @throws \yii\base\Exception
	 * @throws \Twig_Error_Loader
	 * @throws \RuntimeException
	 */
	public function getSettingsHtml(): string {
		// Render the settings template

		return Craft::$app->getView()->renderTemplate(
			'easyaddressfield/_field/_settings',
			[
				'field'     => $this,
				'countries' => $this->getCountries()
			]
		);
	}

	public function rules(): array {

		$addressRules =
			array(
				array(
					array(
						'geoCode'
					),
					'boolean'
				),
				array(
					array(
						'showCoordinates'
					),
					'boolean'
				),
				array(
					array(
						'defaultCountry'
					),
					'string'
				)
			);


		$rules = parent::rules();
		$rules = array_merge( $rules, $addressRules );

		return $rules;
	}

	/**
	 * @param mixed $value
	 * @param ElementInterface|null $element
	 *
	 * @return mixed|EasyAddressFieldModel
	 */
	public function normalizeValue( $value, ElementInterface $element = null ) {
		$settings = $this->getSettings();
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}


		if ( is_array( $value ) && ! empty( array_filter( $value ) ) ) {
			return new EasyAddressFieldModel( $value );
		}

		return null;
	}

	public function serializeValue( $value, ElementInterface $element = null ) {
		$settings = $this->getSettings();
		if ( ! $value ) {
			return $value;
		}

		if ( $settings['geoCode'] and empty( $value['latitude'] ) and empty( $value['longitude'] ) ) {
			$service = new GeoLocationService();
			$value   = $service->geoLocate( $value );

		}

		return Db::prepareValueForDb( $value );
	}


	public function getInputHtml( $value, ElementInterface $element = null ): string {
		// Register our asset bundle
		Craft::$app->getView()->registerAssetBundle( EasyAddressFieldAsset::class );

		// Get our id and namespace
		$id           = Craft::$app->getView()->formatInputId( $this->handle );
		$namespacedId = Craft::$app->getView()->namespaceInputId( $id );

		$pluginSettings = Plugin::getInstance()->getSettings();
		$fieldSettings  = $this->getSettings();

		return $this->renderFormFields( $value );
	}

	protected function renderFormFields( EasyAddressFieldModel $value = null ) {
		// Get our id and namespace
		$id           = Craft::$app->getView()->formatInputId( $this->handle );
		$namespacedId = Craft::$app->getView()->namespaceInputId( $id );

		$fieldSettings  = $this->getSettings();
		$pluginSettings = Plugin::getInstance()->getSettings();

		$fieldLabels   = null;
		$addressFields = null;

		$iconUrl        = Craft::$app->assetManager->getPublishedUrl( '@studioespresso/easyaddressfield/assets', true, 'marker.svg' );
		$pluginSettings = Plugin::getInstance()->getSettings();

		Craft::$app->getView()->registerJsFile( 'https://maps.googleapis.com/maps/api/js?key=' . $pluginSettings->googleApiKey );

		return Craft::$app->getView()->renderTemplate(
			'easyaddressfield/_field/_input',
			[
				'name'           => $this->handle,
				'value'          => $value,
				'field'          => $this,
				'id'             => $id,
				'iconUrl'        => $iconUrl,
				'countries'      => $this->getCountries(),
				'namespacedId'   => $namespacedId,
				'fieldSettings'  => $fieldSettings,
				'pluginSettings' => $pluginSettings,
			]
		);
	}

	protected function getCountries() {
		$data = new ISO3166();
		$data = $data->all();
		$countries = array();
		foreach($data as $country) {
			$countries[$country['alpha2']] = $country['name'];
		};
		return $countries;
	}

}