<?php


namespace Imanaging\CheckFormatBundle;

use Doctrine\ORM\EntityManagerInterface;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedDateCustom;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceDateCustomInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTypeInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormat;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvanced;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedConst;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedString;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatDate;
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

class Mapping
{
  private $em;
  private $converter;
  private $champsPossiblesAIntegrer;
  private $templating;
  private $projectDir;

  /**
   * Mapping constructor.
   * @param EntityManagerInterface $em
   * @param CsvToArrayService $converter
   * @param EngineInterface $templating
   * @param $projectDir
   */
  public function __construct(EntityManagerInterface $em, CsvToArrayService $converter, EngineInterface $templating, $projectDir)
  {
    $this->em = $em;
    $this->converter = $converter;
    $this->champsPossiblesAIntegrer = [];
    $this->templating = $templating;
    $this->projectDir = $projectDir;
  }

  /**
   * @param $mappingId
   * @return JsonResponse|Response
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
        return new Response($this->templating->render('@ImanagingCheckFormat/Mapping/mapping_configuration_avances.html.twig', [
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
   * @param $filesDirectory
   * @return Response
   */
  public function showMappingConfigurationValuesAvancesDetail(MappingConfigurationValueInterface $configurationValue, $filesDirectory){
    $ligneEntete = [];
    $attestationsDir = $this->projectDir.$filesDirectory;
    $fichiersClient = glob($attestationsDir);
    if (count($fichiersClient) == 1) {
      $data = $this->getFirstLinesFromFile($fichiersClient[0], 1);
      $ligneEntete = $data['entete'];
    }

    return new Response($this->templating->render('@ImanagingCheckFormat/Mapping/mapping_configuration_avances_detail.html.twig', [
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
    $mappingsConfigurations = $this->em->getRepository(MappingConfigurationInterface::class)->findAll();
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
   * @param $codeMappingType
   * @return array|bool
   */
  public function getFieldsConfigurationMappingImport($codeMappingType){
    $this->champsPossiblesAIntegrer = $this->getChampsPossiblesAIntegrer($codeMappingType);
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $codeMappingType]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['type' => $mappingConfigurationType, 'active' => true]);
      if ($configuration instanceof MappingConfigurationInterface){
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
                  switch ($champ['type']) {
                    case 'string':
                      // Champs string
                      $fieldtemp = new FieldCheckFormat('string', $champ['data'], $champ['libelle'] .
                        ' (' . $value->getFichierEntete() . ' )', $champ['nullable']);
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
                      break;
                    case 'date':
                      // Champs date
                      $fieldtemp = new FieldCheckFormatDate(
                        $champ['data'],
                        $champ['libelle'] . ' (' . $value->getFichierEntete() . ' )',
                        $champ['nullable'],
                        $value->getMappingType()
                      );
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
                      break;
                    default:
                      return false;
                  }
                } else {
                  return false;
                }
              } else {
                // Champs non intégré
                $fieldtemp = new FieldCheckFormat('string', 'non_integre', $value->getFichierEntete(), true);
                array_push($fields['classic'], $fieldtemp);
              }
            } else {
              $champ = $this->searchChampInPossible($value->getMappingCode());
              if (!is_null($champ)) {
                $libelle = $champ['libelle'];
              } else {
                $libelle = $value->getMappingCode();
              }
              $fieldAdvancedTemp = new FieldCheckFormatAdvanced($value->getMappingCode(), $libelle);

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
                  //                foreach ($value->getMappingConfigurationValueTranslations() as $translation) {
                  //                  if ($translation instanceof MappingConfigurationValueTranslationInterface) {
                  //                    $fieldtemp->addTranslation(new FieldCheckFormatTranslation($translation->getValue(), $translation->getTranslation()));
                  //                  }
                  //                }
                  $fieldAdvancedTemp->addField($fieldtemp);
                } elseif ($avance instanceof MappingConfigurationValueAvanceDateCustomInterface) {
                  $fieldtemp = new FieldCheckFormatAdvancedDateCustom(
                    '',
                    'Date custom',
                    $avance->getFormat(), $avance->getModifier()
                  );
                  $fieldAdvancedTemp->addField($fieldtemp);
                }
              }
              array_push($fields['advanced'], $fieldAdvancedTemp);
            }
          }
        }
        return $fields;
      }
      return false;
    } else {
      return false;
    }
  }

  /**
   * @param $file
   * @param int $nbLines
   * @return array
   */
  public function getFirstLinesFromFile($file, $nbLines = 15){
    $data = $this->converter->convert($file, ';');
    $entete = $data[0];
    if (count($data) < $nbLines) {
      $nbLines = count($data) -1;
    }
    $firstLines = [];
    for ($i = 1; $i <= $nbLines; $i++) {
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
}
