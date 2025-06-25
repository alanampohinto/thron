<?php

namespace Drupal\thron\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\thron\Plugin\EntityBrowser\Widget\THRONSearch;
use Drupal\thron\THRONApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for THRON routes.
 */
class ThronAutocompleteController extends ControllerBase {

  /**
   * The thron_api service.
   *
   * @var \Drupal\thron\THRONApiInterface
   */
  protected $thronApi;

  /**
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The controller constructor.
   *
   * @param \Drupal\thron\THRONApiInterface $thron_api
   *   The thron_api service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value factory.
   */
  public function __construct(THRONApiInterface $thron_api, KeyValueFactoryInterface $key_value_factory) {
    $this->thronApi = $thron_api;
    $this->keyValueFactory = $key_value_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('thron_api'),
      $container->get('keyvalue')
    );
  }

  /**
   * Builds the response.
   */
  public function handleTagsAutocomplete(Request $request, $depth, $classifications) {
    $matches = [];
    $search_key = $request->query->get('q');
    foreach (explode("+", $classifications) as $classification_id) {
      $retrieved_tags = $this->thronApi->getTagsListByClassification($classification_id, [], $depth, $search_key);
      foreach ($retrieved_tags as $key => $tag) {
        $matches[] = [
          'value' => [
            'id' => $tag['id'],
            'name' => $tag['name'],
            'classificationId' => $classification_id,
          ],
          'label' => $tag['name'],
        ];
      }
    }
    return new JsonResponse($matches);
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param $categories
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function handleCategoriesAutocomplete(Request $request, $categories) {
    $results = [];
    $input = $request->query->get('q');
    $categories = json_decode($categories);

    if (!$input) {
      return new JsonResponse($results);
    }

    $input = Xss::filter($input);

    foreach ($categories as $categoryId => $category) {
      if (preg_match("/(" . $input . ")/i", $category) === 1) {
        $parsedName = str_replace(THRONSearch::SUB_CATEGORY_INDENT, '', $category);

        $results[] = [
          'value' => [
            'id' => $categoryId,
            'name' => $parsedName,
          ],
          'label' => $parsedName
        ];
      }
    }

    return new JsonResponse($results);
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   *  @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function handleLanguagesAutocomplete(Request $request) {
    $results = [];
    $query = $request->query->get('q');

    if (!$query) {
      return new JsonResponse($results);
    }

    $languages = $this->getLanguages();

    foreach ($languages as $code => $name) {
      if (stripos($code, $query) !== FALSE || stripos($name, $query) !== FALSE) {
        $results[] = ['value' =>  "$name ($code)", 'label' => "$name ($code)"];
      }
    }

    return new JsonResponse($results);
  }

  private function getLanguages(): array {
    return [
      'ab' => 'Abkhazian',
      'aa' => 'Afar',
      'af' => 'Afrikaans',
      'ak' => 'Akan',
      'sq' => 'Albanian',
      'am' => 'Amharic',
      'ar' => 'Arabic',
      'an' => 'Aragonese',
      'hy' => 'Armenian',
      'as' => 'Assamese',
      'av' => 'Avaric',
      'ae' => 'Avestan',
      'ay' => 'Aymara',
      'az' => 'Azerbaijani',
      'bm' => 'Bambara',
      'ba' => 'Bashkir',
      'eu' => 'Basque',
      'be' => 'Belarusian',
      'bn' => 'Bengali',
      'bh' => 'Bihari',
      'bi' => 'Bislama',
      'bs' => 'Bosnian',
      'br' => 'Breton',
      'bg' => 'Bulgarian',
      'my' => 'Burmese',
      'ca' => 'Catalan',
      'ch' => 'Chamorro',
      'ce' => 'Chechen',
      'ny' => 'Chichewa',
      'zh' => 'Chinese',
      'cv' => 'Chuvash',
      'kw' => 'Cornish',
      'co' => 'Corsican',
      'cr' => 'Cree',
      'hr' => 'Croatian',
      'cs' => 'Czech',
      'da' => 'Danish',
      'dv' => 'Divehi',
      'nl' => 'Dutch',
      'dz' => 'Dzongkha',
      'en' => 'English',
      'eo' => 'Esperanto',
      'et' => 'Estonian',
      'ee' => 'Ewe',
      'fo' => 'Faroese',
      'fj' => 'Fijian',
      'fi' => 'Finnish',
      'fr' => 'French',
      'ff' => 'Fulah',
      'gl' => 'Galician',
      'ka' => 'Georgian',
      'de' => 'German',
      'el' => 'Greek',
      'gn' => 'Guarani',
      'gu' => 'Gujarati',
      'ht' => 'Haitian',
      'ha' => 'Hausa',
      'he' => 'Hebrew',
      'hz' => 'Herero',
      'hi' => 'Hindi',
      'ho' => 'Hiri Motu',
      'hu' => 'Hungarian',
      'ia' => 'Interlingua',
      'id' => 'Indonesian',
      'ie' => 'Interlingue',
      'ga' => 'Irish',
      'ig' => 'Igbo',
      'ik' => 'Inupiaq',
      'io' => 'Ido',
      'is' => 'Icelandic',
      'it' => 'Italian',
      'iu' => 'Inuktitut',
      'ja' => 'Japanese',
      'jv' => 'Javanese',
      'kl' => 'Kalaallisut',
      'kn' => 'Kannada',
      'kr' => 'Kanuri',
      'ks' => 'Kashmiri',
      'kk' => 'Kazakh',
      'km' => 'Central Khmer',
      'ki' => 'Kikuyu',
      'rw' => 'Kinyarwanda',
      'ky' => 'Kirghiz',
      'kv' => 'Komi',
      'kg' => 'Kongo',
      'ko' => 'Korean',
      'ku' => 'Kurdish',
      'kj' => 'Kuanyama',
      'la' => 'Latin',
      'lb' => 'Luxembourgish',
      'lg' => 'Ganda',
      'li' => 'Limburgan',
      'ln' => 'Lingala',
      'lo' => 'Lao',
      'lt' => 'Lithuanian',
      'lu' => 'Luba-Katanga',
      'lv' => 'Latvian',
      'gv' => 'Manx',
      'mk' => 'Macedonian',
      'mg' => 'Malagasy',
      'ms' => 'Malay',
      'ml' => 'Malayalam',
      'mt' => 'Maltese',
      'mi' => 'Maori',
      'mr' => 'Marathi',
      'mh' => 'Marshallese',
      'mn' => 'Mongolian',
      'na' => 'Nauru',
      'nv' => 'Navajo',
      'nd' => 'North Ndebele',
      'ne' => 'Nepali',
      'ng' => 'Ndonga',
      'nb' => 'Norwegian Bokmål',
      'nn' => 'Norwegian Nynorsk',
      'no' => 'Norwegian',
      'ii' => 'Sichuan Yi',
      'nr' => 'South Ndebele',
      'oc' => 'Occitan',
      'oj' => 'Ojibwa',
      'cu' => 'Church Slavic',
      'om' => 'Oromo',
      'or' => 'Oriya',
      'os' => 'Ossetian',
      'pa' => 'Punjabi',
      'pi' => 'Pali',
      'fa' => 'Persian',
      'pl' => 'Polish',
      'ps' => 'Pashto',
      'pt' => 'Portuguese',
      'qu' => 'Quechua',
      'rm' => 'Romansh',
      'rn' => 'Rundi',
      'ro' => 'Romanian',
      'ru' => 'Russian',
      'sa' => 'Sanskrit',
      'sc' => 'Sardinian',
      'sd' => 'Sindhi',
      'se' => 'Northern Sami',
      'sm' => 'Samoan',
      'sg' => 'Sango',
      'sr' => 'Serbian',
      'gd' => 'Gaelic',
      'sn' => 'Shona',
      'si' => 'Sinhala',
      'sk' => 'Slovak',
      'sl' => 'Slovenian',
      'so' => 'Somali',
      'st' => 'Southern Sotho',
      'es' => 'Spanish',
      'su' => 'Sundanese',
      'sw' => 'Swahili',
      'ss' => 'Swati',
      'sv' => 'Swedish',
      'ta' => 'Tamil',
      'te' => 'Telugu',
      'tg' => 'Tajik',
      'th' => 'Thai',
      'ti' => 'Tigrinya',
      'bo' => 'Tibetan',
      'tk' => 'Turkmen',
      'tl' => 'Tagalog',
      'tn' => 'Tswana',
      'to' => 'Tonga',
      'tr' => 'Turkish',
      'ts' => 'Tsonga',
      'tt' => 'Tatar',
      'tw' => 'Twi',
      'ty' => 'Tahitian',
      'ug' => 'Uighur',
      'uk' => 'Ukrainian',
      'ur' => 'Urdu',
      'uz' => 'Uzbek',
      've' => 'Venda',
      'vi' => 'Vietnamese',
      'vo' => 'Volapük',
      'wa' => 'Walloon',
      'cy' => 'Welsh',
      'wo' => 'Wolof',
      'xh' => 'Xhosa',
      'yi' => 'Yiddish',
      'yo' => 'Yoruba',
      'za' => 'Zhuang',
      'zu' => 'Zulu',
    ];  
  }
}
