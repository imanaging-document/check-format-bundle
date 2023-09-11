<?php

namespace Imanaging\CheckFormatBundle;

use App\Entity\MappingConfiguration;
use App\Entity\MappingConfigurationFile;
use App\Entity\MappingConfigurationSkippingRule;
use App\Entity\MappingConfigurationType;
use App\Entity\MappingConfigurationValueAvanceAutoIncrement;
use App\Entity\MappingConfigurationValueAvanceFileTransformation;
use Countable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedAutoIncrement;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedDateCustom;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedMultiColumnArray;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedSaisieManuelle;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatBoolean;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatFloat;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatInteger;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationCuttingRuleInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationSkippingRuleInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceAutoIncrementInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceDateCustomInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileTransformationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceMultiColumnArrayInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceSaisieManuelleInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTypeInterface;
use Imanaging\CheckFormatBundle\Service\ExcelToArrayService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormat;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvanced;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedConst;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedString;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatDate;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatArray;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatTransformation;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatTranslation;
use Imanaging\CheckFormatBundle\Interfaces\MappingChampPossibleInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTextInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTransformationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTranslationInterface;
use Imanaging\CheckFormatBundle\Service\CsvToArrayService;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Mapping
{
  private $em;
  private $converter;
  private $champsPossiblesAIntegrer;
  private $projectDir;
  private $excelConverter;
  private $twig;

  /**
   * Mapping constructor.
   * @param EntityManagerInterface $em
   * @param CsvToArrayService $converter
   * @param Environment $twig
   * @param $projectDir
   * @param ExcelToArrayService $excelConverter
   */
  public function __construct(EntityManagerInterface $em, CsvToArrayService $converter, Environment $twig, $projectDir, ExcelToArrayService $excelConverter)
  {
    $this->em = $em;
    $this->converter = $converter;
    $this->champsPossiblesAIntegrer = [];
    $this->projectDir = $projectDir;
    $this->excelConverter = $excelConverter;
    $this->twig = $twig;
  }

  public function getProjectDir()
  {
    return $this->projectDir;
  }

  /**
   * @param $mappingId
   * @return JsonResponse|Response
   * @throws LoaderError
   * @throws RuntimeError
   * @throws SyntaxError
   */
  public function showMappingConfigurationValuesAvances($mappingId){
    $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($mappingId);
    if ($configuration instanceof MappingConfigurationInterface) {
      if ($this->setMappingConfigurationActive($configuration)) {
        $values = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy(['mappingConfiguration' => $configuration, 'fichierIndex' => null]);
        $res = $this->getChampsAMapper($configuration->getId(), false);
        if ($res['error']) {
          return new JsonResponse($res, 500);
        }

        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_avances.html.twig', [
          'mapping_configuration_type' => $configuration->getType(),
          'values_avances' => $values,
          'champs_possibles' => $res['champs_a_mapper'],
          'value' => $configuration
        ]));
      } else {
        return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration active'], 500);
      }
    } else {
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (ID non trouvé : '.$mappingId.')'], 500);
    }
  }

  /**
   * @param MappingConfigurationValueInterface $configurationValue
   * @return Response
   * @throws LoaderError
   * @throws RuntimeError
   * @throws SyntaxError
   */
  public function showMappingConfigurationValuesAvancesDetail(MappingConfigurationValueInterface $configurationValue){
    $ligneEntete = [];
    $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(
      ['active' => true, 'type' => $configurationValue->getMappingConfiguration()->getType()]);
    if ($mappingConfiguration instanceof MappingConfiguration) {
      $cuttingRules = $mappingConfiguration->getMappingConfigurationCuttingRules();
      $skippingRules = $mappingConfiguration->getMappingConfigurationSkippingRules();
    } else {
      $cuttingRules = [];
      $skippingRules = [];
    }
    $directory = $this->projectDir.$configurationValue->getMappingConfiguration()->getType()->getFilesDirectory() .
      $configurationValue->getMappingConfiguration()->getType()->getFilename() . '*';
    $fichiersClient = glob($directory);
    if (count($fichiersClient) == 1) {
      $data = $this->getFirstLinesFromFile($fichiersClient[0], 1, $cuttingRules, $skippingRules);
      $ligneEntete = $data['entete'];
    }

    return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_avances_detail.html.twig', [
      'values_avances' => $configurationValue->getMappingConfigurationValueAvances(),
      'types_values' => $this->em->getRepository(MappingConfigurationValueAvanceTypeInterface::class)->findAll(),
      'value' => $configurationValue,
      'ligne_entete' => $ligneEntete
    ]));
  }

  /**
   * @param $champsPossibles
   */
  public function updateChampsPossiblesAIntegrerEnMasse($champsPossibles){
    foreach ($champsPossibles as $champPossible){
      $this->updateChampPossibleAIntegrer($champPossible['champ_possible'], $champPossible['visibilite'], $champPossible['libelle']);
    }
  }

  /**
   * @param MappingChampPossibleInterface $champPossible
   * @param $visibilite
   * @param $libelle
   */
  public function updateChampPossibleAIntegrer(MappingChampPossibleInterface $champPossible, $visibilite, $libelle){
    $champPossible->setVisible($visibilite);
    $champPossible->setLibelle($libelle);
    $this->em->persist($champPossible);
    $this->em->flush();
  }

  /**
   * @param $codeMappingType
   * @return array
   */
  public function getChampsPossiblesAIntegrer($codeMappingType){
    $formatted = [];
    $type = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $codeMappingType]);
    if ($type instanceof MappingConfigurationTypeInterface){
      $champsAIntegrer = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy(['mappingConfigurationType' => $type, 'visible' => true]);
      foreach ($champsAIntegrer as $champ){
        if ($champ instanceof MappingChampPossibleInterface){
          $formattedChamp['table'] = $champ->getTable();
          $formattedChamp['data'] = $champ->getData();
          $formattedChamp['libelle'] = $champ->getLibelle();
          $formattedChamp['type'] = $champ->getType();
          $formattedChamp['obligatoire'] = $champ->isObligatoire();
          $formattedChamp['nullable'] = $champ->isNullable();
          $formattedChamp['integration_local'] = $champ->isIntegrationLocal();
          $formattedChamp['valeurs_possibles'] = $champ->getValeursPossibles();
          $formatted[] = $formattedChamp;
        }
      }
    }

    return $formatted;
  }

  /**
   * @param $champDataSelect
   * @param $codeMappingType
   * @return mixed|null
   */
  public function getChampPossibleByCode($champDataSelect, $codeMappingType)
  {
    $champSelect = null;
    $champs = $this->getChampsPossiblesAIntegrer($codeMappingType);
    foreach ($champs as $champ) {
      if ($champDataSelect == $champ['data']) {
        $champSelect = $champ;
      }
    }
    return $champSelect;
  }

  /**
   * @param $mappingId
   * @param $obligatoireOnly
   * @return array
   */
  public function getChampsAMapper($mappingId, $obligatoireOnly)
  {
    $result = ['error' => false, 'champs_a_mapper' => []];
    $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($mappingId);
    if ($configuration instanceof MappingConfigurationInterface) {
      $champsAIntegrer = $this->getChampsPossiblesAIntegrer($configuration->getType()->getCode());
      foreach ($champsAIntegrer as $champ) {
        if (!$obligatoireOnly || $champ['obligatoire']) {
          $result['champs_a_mapper'][$champ['data']] = ['libelle' => $champ['libelle']];
        }
      }
      $values = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy(['mappingConfiguration' => $configuration]);
      foreach ($values as $value) {
        if ($value instanceof MappingConfigurationValueInterface) {
          if (!(is_null($value->getMappingCode()))) {
            unset($result['champs_a_mapper'][$value->getMappingCode()]);
          }
        }
      }
    } else {
      $result['error'] = true;
      $result['error_message'] = 'Une erreur est survenue lors de la récupération de la configuration (ID non trouvé)';
    }
    return $result;
  }

  /**
   * @param MappingConfigurationInterface $mappingConfiguration
   * @return bool
   */
  public function setMappingConfigurationActive(MappingConfigurationInterface $mappingConfiguration)
  {
    $mappingActifSaved = false;
    $mappingsConfigurations = $this->em->getRepository(MappingConfigurationInterface::class)->findBy(['type' => $mappingConfiguration->getType()]);
    foreach ($mappingsConfigurations as $configuration) {
      if ($configuration instanceof MappingConfigurationInterface) {
        if ($configuration->getId() == $mappingConfiguration->getId()) {
          $configuration->setActive(true);
          $mappingActifSaved = true;
        } else {
          $configuration->setActive(false);
        }
        $this->em->persist($configuration);
      }
    }
    $this->em->flush();
    return $mappingActifSaved;
  }

  /**
   * @param MappingConfigurationTypeInterface $mappingConfigurationType
   * @param $withEntete
   * @return array
   * @throws Exception
   */
  public function controlerFichiers(MappingConfigurationInterface $mappingConfiguration, $withEntete){
    $result = [
      'nb_lines' => 0,
      'error' => false,
      'error_list' => []
    ];
    // On parcourt les fichiers un à un
    $filesDirectory = $mappingConfiguration->getType()->getFilesDirectory() . $mappingConfiguration->getType()->getFilename() . '*';
    foreach (glob($this->projectDir.$filesDirectory) as $fichier){
      $mappingConfigurationFile = $this->em->getRepository(MappingConfigurationFileInterface::class)->findOneBy([
        'filename' => basename($fichier), 'mappingConfiguration' => $mappingConfiguration
      ]);

      if ($mappingConfigurationFile instanceof MappingConfigurationFileInterface) {
        // On parse le fichier CSV
        $res =  $this->getDataFromFile($fichier, $mappingConfiguration->getMappingConfigurationCuttingRules(),
          $mappingConfiguration->getMappingConfigurationSkippingRules());
        $lignes = $res['data'];
        $withEntete= $res['entete'];
        if ($withEntete){
          unset($lignes[0]);
        }
        $fields = $this->getFieldsConfigurationMappingImport($mappingConfiguration);
        if ($fields) {
          $result = CheckFormatFile::checkFormatFile($fields['classic'], $fields['advanced'], $lignes, $mappingConfigurationFile->getValuesSaisiesManuelles());
        } else {
          return [
            'error' => true,
            'error_message' => 'Une erreur est survenue lors de la récupération des champs de mapping'
          ];
        }
      } else {
        return [
          'error' => true,
          'error_message' => 'Une erreur est survenue lors de la récupération du fichier'
        ];
      }

    }
    return $result;
  }

  /**
   * @param $codeMappingType
   * @return array|bool
   */
  public function getFieldsConfigurationMappingImport(MappingConfigurationInterface $configuration){
    $this->champsPossiblesAIntegrer = $this->getChampsPossiblesAIntegrer($configuration->getType()->getCode());
    $fields = [
      'classic' => [],
      'advanced' => []
    ];

    foreach ($configuration->getMappingConfigurationValues() as $value) {
      if ($value instanceof MappingConfigurationValueInterface) {
        if (!is_null($value->getFichierIndex())) {
          if (!is_null($value->getMappingCode())) {
            $champ = $this->searchChampInPossible($value->getMappingCode());
            if (!is_null($champ)) {
              $code = $champ['data'];
              $libelle = $champ['libelle'] .' (' . $value->getFichierEntete() . ' )';
              $nullable = $champ['nullable'];
              $valeursPossibles = $champ['valeurs_possibles'];
              switch ($champ['type']) {
                case 'string':
                  $fieldtemp = new FieldCheckFormat('string', $code, $libelle, $nullable, $valeursPossibles);
                  break;
                case 'date':
                  $fieldtemp = new FieldCheckFormatDate(
                    $code, $libelle, $nullable, $valeursPossibles,
                    $value->getMappingType()
                  );
                  break;
                case 'boolean':
                  $fieldtemp = new FieldCheckFormatBoolean($code, $libelle, $nullable, $valeursPossibles);
                  break;
                case 'integer':
                  $fieldtemp = new FieldCheckFormatInteger($code, $libelle, $nullable, $valeursPossibles);
                  break;
                case 'float':
                  $fieldtemp = new FieldCheckFormatFloat($code, $libelle, $nullable, $valeursPossibles);
                  break;
                case 'array':
                  $fieldtemp = new FieldCheckFormatArray($code, $libelle, $nullable, $valeursPossibles,
                    $value->getMappingType());
                  break;
                default:
                  return false;
              }
              foreach ($value->getMappingConfigurationValueTranslations() as $translation) {
                if ($translation instanceof MappingConfigurationValueTranslationInterface) {
                  $fieldtemp->addTranslation(
                    new FieldCheckFormatTranslation(
                      $translation->getValue(),
                      $translation->getTranslation()
                    )
                  );
                }
              }
              foreach ($value->getMappingConfigurationValueTransformations() as $transformation) {
                if ($transformation instanceof MappingConfigurationValueTransformationInterface) {
                  $fieldtemp->addTransformation(
                    new FieldCheckFormatTransformation(
                      $transformation->getTransformation(),
                      $transformation->getNbCaract()
                    )
                  );
                }
              }
              array_push($fields['classic'], $fieldtemp);
            } else {
              var_dump($value->getMappingCode());
              die;
              return false;
            }
          } else {
            // Champs non intégré
            $fieldtemp = new FieldCheckFormat('string', 'non_integre', $value->getFichierEntete(), true, []);
            array_push($fields['classic'], $fieldtemp);
          }
        } else {
          $champ = $this->searchChampInPossible($value->getMappingCode());
          if (!is_null($champ)) {
            $libelle = $champ['libelle'];$champ = $this->searchChampInPossible($value->getMappingCode());
            $valeursPossibles = $champ['valeurs_possibles'];
          } else {
            $libelle = $value->getMappingCode();
            $valeursPossibles = [];
          }
          $fieldAdvancedTemp = new FieldCheckFormatAdvanced($value->getMappingCode(), $libelle, $valeursPossibles);

          foreach ($value->getMappingConfigurationValueAvances() as $avance) {
            if ($avance instanceof MappingConfigurationValueAvanceTextInterface) {
              $fieldtemp = new FieldCheckFormatAdvancedConst(
                '',
                'Valeur saisie : ' . $avance->getValue(),
                $avance->getValue()
              );
              $fieldAdvancedTemp->addField($fieldtemp);
            } elseif ($avance instanceof MappingConfigurationValueAvanceFileInterface) {
              // TODO GESTION DES TRANSLATION
              $fieldtemp = new FieldCheckFormatAdvancedString(
                '',
                $value->getMappingCode() . ' (' . $avance->getFichierEntete() . ' )',
                $avance->getFichierIndex(),
                $champ['nullable']
              );

              foreach ($avance->getMappingConfigurationValueAvanceFileTransformations() as $transformation) {
                if ($transformation instanceof MappingConfigurationValueAvanceFileTransformationInterface) {
                  $fieldtemp->addTransformation(
                    new FieldCheckFormatTransformation(
                      $transformation->getTransformation(),
                      $transformation->getNbCaract()
                    )
                  );
                }
              }
              $fieldAdvancedTemp->addField($fieldtemp);
            } elseif ($avance instanceof MappingConfigurationValueAvanceDateCustomInterface) {
              $fieldtemp = new FieldCheckFormatAdvancedDateCustom(
                '',
                'Date custom',
                $avance->getFormat(), $avance->getModifier()
              );
              $fieldAdvancedTemp->addField($fieldtemp);
            } elseif ($avance instanceof MappingConfigurationValueAvanceMultiColumnArrayInterface) {
              $fieldtemp = new FieldCheckFormatAdvancedMultiColumnArray(
                '',
                'Tableau multi colonne',
                $avance->getDelimiter(), $avance->getColumns()
              );
              $fieldAdvancedTemp->addField($fieldtemp);
            } elseif ($avance instanceof MappingConfigurationValueAvanceAutoIncrementInterface) {
              $fieldtemp = new FieldCheckFormatAdvancedAutoIncrement('Auto incrément');
              $fieldAdvancedTemp->addField($fieldtemp);
            }  elseif ($avance instanceof MappingConfigurationValueAvanceSaisieManuelleInterface) {
              $fieldtemp = new FieldCheckFormatAdvancedSaisieManuelle('Saisie manuelle');
              $fieldtemp->setIdValueAvance($avance->getId());
              $fieldAdvancedTemp->addField($fieldtemp);
            }
          }

          foreach ($value->getMappingConfigurationValueTranslations() as $translation) {
            if ($translation instanceof MappingConfigurationValueTranslationInterface) {
              $fieldAdvancedTemp->addTranslation(
                new FieldCheckFormatTranslation(
                  $translation->getValue(),
                  $translation->getTranslation()
                )
              );
            }
          }
          foreach ($value->getMappingConfigurationValueTransformations() as $transformation) {
            if ($transformation instanceof MappingConfigurationValueTransformationInterface) {
              $fieldAdvancedTemp->addTransformation(
                new FieldCheckFormatTransformation(
                  $transformation->getTransformation(),
                  $transformation->getNbCaract()
                )
              );
            }
          }
          array_push($fields['advanced'], $fieldAdvancedTemp);
        }
      }
    }
    return $fields;
  }

  /**
   * @param $codeMappingType
   * @return array|bool
   */
  public function getValueAvancesSaisieManuelleConfigurationMappingImport(MappingConfigurationInterface $configuration){
    $fields = [];

    foreach ($configuration->getMappingConfigurationValues() as $value) {
      if ($value instanceof MappingConfigurationValueInterface) {
        foreach ($value->getMappingConfigurationValueAvances() as $avance) {
          if ($avance instanceof MappingConfigurationValueAvanceSaisieManuelleInterface) {
            $fields[] = $avance;
          }
        }
      }
    }
    return $fields;
  }

  /**
   * @param $file
   * @param int $nbLines
   * @param array $cuttingRules
   * @param array $skippingRules
   * @return array
   */
  public function getFirstLinesFromFile($file, $nbLines = 15, $cuttingRules = [], $skippingRules = []){
    $res = $this->getDataFromFile($file, $cuttingRules, $skippingRules);

    $withEntete= $res['entete'];
    $data = $res['data'];
    if ($withEntete) {
      $entete = $data[0];
    } else {
      $entete = ['no_entete'];
    }
    if (count($data) < $nbLines) {
      $nbLines = count($data) - $withEntete;
    }
    $firstLines = [];
    for ($i = 1; $i < $nbLines; $i++) {
      array_push($firstLines, $data[$i]);
    }

    return [
      'entete' => $entete,
      'first_lines' =>
        $firstLines
    ];
  }

  /**
   * @param $code
   * @return mixed|null
   */
  private function searchChampInPossible($code)
  {
    foreach ($this->champsPossiblesAIntegrer as $champsPossible) {
      if ($champsPossible['data'] == $code) {
        return $champsPossible;
      }
    }
    return null;
  }

  public function getDataFromFile($file, $cuttingRules = [], $skippingRules = [])
  {
    switch (pathinfo($file, PATHINFO_EXTENSION)) {
      case 'xlsx':
      case 'xls':
        $entete = true;
        $data = $this->excelConverter->convert($file);
        break;
      case "csv":
        $entete = true;
        $data = $this->converter->convert($file, ';');
        break;
      case "txt":
        $entete = true;
        $lines = $this->converter->getFromTxt($file, ';');
        $data = [];
        $enteteTmp = [];
        if (count($cuttingRules) > 0) {
          foreach ($cuttingRules as $rule) {
            if ($rule instanceof MappingConfigurationCuttingRuleInterface) {
             $enteteTmp[] = $rule->getLabel();
            }
          }
        } else {
          $enteteTmp[] = 'Header 0';
        }
        $data[] = $enteteTmp;
        foreach ($lines as $line) {
          if (!$this->skipValue($line, $skippingRules)) {
            $data[] = $this->cutValues($line, $cuttingRules);
          }
        }
        break;
      default:
        var_dump('L\'extention ' . pathinfo($file, PATHINFO_EXTENSION) . ' du fichier n\'est pas géré par ce module.');
        die;
    }
    return ['data' => $data, 'entete' => $entete];
  }

  private function cutValues(string $value, $cuttingRules) : array
  {
    $data = [];
    if (count($cuttingRules) > 0) {
      foreach ($cuttingRules as $rule) {
        if ($rule instanceof MappingConfigurationCuttingRuleInterface) {
          $data[] = trim(mb_substr($value, $rule->getOffset(), $rule->getLength()));
        }
      }
    } else {
      $data[] = $value;
    }
    return $data;
  }

  /**
   * @param MappingConfigurationInterface $mappingConfiguration
   * @return array|false
   */
  public function getFichiersLocauxByDateModification(MappingConfigurationInterface $mappingConfiguration)
  {
    $filesDirectory = $mappingConfiguration->getType()->getFilesDirectory() . $mappingConfiguration->getType()->getFilename() . '*';
    // On vérifie qu'il n'y a pas de fichier en attente d'intégration déjà copié dans le répértoire local
    $fichiersLocaux = glob($this->projectDir.$filesDirectory);
    // On trie les fichiers par date de dépot
    usort($fichiersLocaux, function( $a, $b ) { return filemtime($a) - filemtime($b); });
    return $fichiersLocaux;
  }

  /**
   * @throws Exception
   */
  private function skipValue(string $value, mixed $skippingRules): bool
  {
    if (count($skippingRules) > 0) {
      foreach ($skippingRules as $skippingRule) {
        if ($skippingRule instanceof MappingConfigurationSkippingRuleInterface) {
          switch ($skippingRule->getType()) {
            case MappingConfigurationSkippingRuleInterface::TYPE_FIRSTS_CHAR_VALUES:
              if (in_array(substr($value, 0, $skippingRule->getDatas()['nb_chars']), $skippingRule->getDatas()['values'])){
                return true;
              }
              break;
            default:
              throw new Exception('Type de skipping rules non géré : ' . $skippingRule->getType());
          }
        }
      }
    }
    return false;
  }
}
