<?php


namespace Imanaging\CheckFormatBundle\Controller;

use DateTime;
use Exception;
use Imanaging\CheckFormatBundle\Enum\TransformationEnum;
use Imanaging\CheckFormatBundle\Interfaces\MappingChampPossibleInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTextInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTransformationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTranslationInterface;
use Imanaging\CheckFormatBundle\Mapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MappingController extends AbstractController
{
  private $em;
  private $mapping;
  private $projectDir;

  /**
   * MappingController constructor.
   * @param EntityManagerInterface $em
   * @param Mapping $mapping
   * @param $projectDir
   */
  public function __construct(EntityManagerInterface $em, Mapping $mapping, $projectDir)
  {
    $this->em = $em;
    $this->mapping = $mapping;
    $this->projectDir = $projectDir;
  }

  public function mappingPageAction(Request $request){
    $params = $request->request->all();
    $filesDir = $params['files_directory'];
    $attestationsDir = $this->projectDir . $filesDir;
    $fichiersClient = glob($attestationsDir);
    if (count($fichiersClient) == 1) {
      if (isset($params['mapping_configuration_type'])){
        $data = $this->mapping->getFirstLinesFromFile($fichiersClient[0], 10);
        $ligneEntete = $data['entete'];
        $lignes = $data['first_lines'];

        return $this->render("@ImanagingCheckFormat/Mapping/mapping_page.html.twig", [
          'champs' => $this->mapping->getChampsPossiblesAIntegrer($params['mapping_configuration_type']),
          'ligne_entete' => $ligneEntete,
          'lignes' => $lignes,
          'fichiers_en_attente' => $fichiersClient,
          'mapping_configuration_type' => $params['mapping_configuration_type'],
          'files_directory' => $params['files_directory'],
          'next_step_route' => $params['next_step_route'],
        ]);
      } else {
        return new JsonResponse([], 500);
      }
    } else {
      return $this->render("@ImanagingCheckFormat/Mapping/no_fichier_to_map.html.twig", [
        'files_directory' => $params['files_directory'],
      ]);
    }
  }

  public function uploadFileAction(Request $request){
    $params = $request->request->all();
    $dir = $this->projectDir.dirname($params['files_directory']);
    if (!is_dir($dir)){
      mkdir($dir, true);
    }

    $files = $request->files->all();
    $fichier = $files['file'];
    if ($fichier instanceof UploadedFile){
      try {
        $now = new DateTime();
        $newFileName = 'fichier_client_'.$now->format('YmdHis').'.'.$fichier->guessExtension();
        $fichier->move($dir, $newFileName);
        return new JsonResponse();
      } catch (Exception $e){
        return new JsonResponse(["error_message" => "Une erreur est survenue lors de l\'envoi du fichier :("], 500);
      }
    } else {
      return new JsonResponse(["error_message" => "Veuillez soumettre un fichier valide."], 500);
    }
  }

  public function deleteFileAction(Request $request){
    $params = $request->request->all();
    $dir = $this->projectDir.dirname($params['files_directory']);
    if (is_dir($dir)){
      if (file_exists($dir.'/'.$params['filename'])){
        try{
          unlink($dir.'/'.$params['filename']);
          return new JsonResponse();
        } catch (Exception $e){
          return new JsonResponse([], 500);
        }
      } else {
        return new JsonResponse([], 500);
      }
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function gererChampsPossiblesAction(Request $request){
    $params = $request->request->all();
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $params['mapping_configuration_type']]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $champsPossibles = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy(['mappingConfigurationType' => $mappingConfigurationType, 'visible' => true]);
      return $this->render("@ImanagingCheckFormat/Mapping/gerer_champs_possibles.html.twig", [
        'mapping_configuration_type' => $mappingConfigurationType,
        'champsPossibles' => $champsPossibles,
      ]);
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function modelEditChampPossibleAction(Request $request){
    $params = $request->request->all();
    $champPossible = $this->em->getRepository(MappingChampPossibleInterface::class)->find($params['champ_id']);
    if ($champPossible instanceof MappingChampPossibleInterface){
      return $this->render("@ImanagingCheckFormat/Mapping/modals/edit_champ_possible.html.twig", [
        'champPossible' => $champPossible
      ]);
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function saveChampPossibleAction(Request $request){
    $params = $request->request->all();
    $champPossible = $this->em->getRepository(MappingChampPossibleInterface::class)->find($params['champ_id']);
    if ($champPossible instanceof MappingChampPossibleInterface){
      $champPossible->setLibelle($params['libelle']);
      $champPossible->setTable($params['table']);
      $champPossible->setType($params['type']);
      $champPossible->setObligatoire(isset($params['obligatoire']) && $params['obligatoire']);
      $champPossible->setNullable(isset($params['nullable']) && $params['nullable']);
      $champPossible->setIntegrationLocal(isset($params['integrationLocal']) && $params['integrationLocal']);
      $this->em->persist($champPossible);
      $this->em->flush();
      return new JsonResponse();
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function toggleBooleanValueChampPossibleAction(Request $request){
    $params = $request->request->all();
    $champPossible = $this->em->getRepository(MappingChampPossibleInterface::class)->find($params['champ_id']);
    if ($champPossible instanceof MappingChampPossibleInterface){
      if ($params['type'] == 'obligatoire'){
        $res = !$champPossible->isObligatoire();
        $champPossible->setObligatoire(!$champPossible->isObligatoire());
      } elseif($params['type'] == 'nullable'){
        $res = !$champPossible->isNullable();
        $champPossible->setNullable(!$champPossible->isNullable());
      } elseif($params['type'] == 'integration-local'){
        $res = !$champPossible->isIntegrationLocal();
        $champPossible->setIntegrationLocal(!$champPossible->isIntegrationLocal());
      } else {
        return new JsonResponse([], 500);
      }
      $this->em->persist($champPossible);
      $this->em->flush();
      return new JsonResponse(['res' => $res]);
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function getMappingConfigurationSelectAction(){
    $mappingConfigurations = $this->em->getRepository(MappingConfigurationInterface::class)->findAll();
    return $this->render('@ImanagingCheckFormat/Mapping/partials/mapping_configuration_select.html.twig', [
      'mapping_configurations' => $mappingConfigurations
    ]);
  }

  public function addMappingConfigurationAction(Request $request){
    $params = $request->request->all();
    $em = $this->getDoctrine()->getManager();
    if (isset($params['libelle'])) {
      $libelle = $params['libelle'];
      $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $params['mapping_configuration_type']]);
      if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
        $className = $this->em->getRepository(MappingConfigurationInterface::class)->getClassName();
        $configuration = new $className();
        if ($configuration instanceof MappingConfigurationInterface){
          $configuration->setType($mappingConfigurationType);
          $configuration->setLibelle($libelle);
          $configuration->setActive(true);
          $em->persist($configuration);
          $em->flush();
          return new JsonResponse();
        } else {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'ajout de la configuration'], 500);
        }
      } else {
        return new JsonResponse(['error' => true, 'error_message' => 'Type de configuration introuvable !'], 500);

      }
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'ajout de la configuration'], 500);
  }

  public function removeMappingConfigurationValuesAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        try {
          foreach ($configuration->getMappingConfigurationValues() as $value) {
            if ($value instanceof MappingConfigurationValueInterface) {
              foreach ($value->getMappingConfigurationValueTranslations() as $translation) {
                $this->em->remove($translation);
              }
              $this->em->remove($value);
            }
          }
          $this->em->remove($configuration);
          $this->em->flush();
        } catch (Exception $e) {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la suppression de la configuration'], 500);
        }
        return new JsonResponse();
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la suppression de la configuration (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la suppression de la configuration (paramètre manquant)'], 500);
  }

  public function addConfigurationValuesAvancesAction(Request $request, $id)
  {
    $params = $request->request->all();
    $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($id);
    if ($configuration instanceof MappingConfigurationInterface){
      $className = $this->em->getRepository(MappingConfigurationValueInterface::class)->getClassName();
      $configurationValue = new $className();
      if ($configurationValue instanceof MappingConfigurationValueInterface){
        $configurationValue->setMappingCode($params['type']);
        $configurationValue->setMappingConfiguration($configuration);
        $this->em->persist($configurationValue);
        $this->em->flush();
        return $this->mapping->showMappingConfigurationValuesAvances($params['mapping_id']);
      } else {
        return new JsonResponse(['error_message' => 'Impossible de créer la valeur.'], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Configuration introuvable.'], 500);
    }
  }

  public function getMappingConfigurationValuesAvancesDetailAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['configuration_avance_id'])) {
      $configurationValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($params['configuration_avance_id']);
      if ($configurationValue instanceof MappingConfigurationValueInterface){
        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue, $params['files_directory']);
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (paramètre manquant)'], 500);
  }

  public function removeConfigurationValuesAvancesAction(Request $request)
  {
    $params = $request->request->all();
    $valueAvance = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($params['mapping_value_id']);
    if ($valueAvance instanceof MappingConfigurationValueInterface) {
      try {
        foreach ($valueAvance->getMappingConfigurationValueAvances() as $avance) {
          $this->em->remove($avance);
        }
        $this->em->remove($valueAvance);
        $this->em->flush();
        return $this->mapping->showMappingConfigurationValuesAvances($params['mapping_id']);
      } catch (Exception $e) {
        return new JsonResponse(['error_message' => 'Impossible de supprimer la configuration avancée : '.$e->getMessage()], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Impossible de supprimer la configuration avancée.'], 500);
    }
  }

  public function addConfigurationValueAvanceDetailAction(Request $request, $id)
  {
    $params = $request->request->all();
    $configurationValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($id);
    if ($configurationValue instanceof MappingConfigurationValueInterface){
      $type = $this->em->getRepository(MappingConfigurationValueAvanceTypeInterface::class)->findOneBy(['code' => $params['type']]);
      if ($type instanceof MappingConfigurationValueAvanceTypeInterface){
        // on ajoute la value avancée
        if ($type->getCode() == 'value_file') {
          $className = $this->em->getRepository(MappingConfigurationValueAvanceFileInterface::class)->getClassName();
          $value = new $className();
          if ($value instanceof MappingConfigurationValueAvanceFileInterface){
            $value->setFichierEntete($params['file_entete']);
            $value->setFichierIndex($params['file_index']);
          }
        } else {
          if (isset($params['value'])){
            $className = $this->em->getRepository(MappingConfigurationValueAvanceTextInterface::class)->getClassName();
            $value = new $className();
            if ($value instanceof MappingConfigurationValueAvanceTextInterface){
              $value->setValue($params['value']);
            }
          } else {
            return new JsonResponse(['error_message' => 'Impossible de récuperer la valeur saisie.'], 500);
          }
        }
        $value->setMappingConfigurationValue($configurationValue);
        $value->setMappingConfigurationValueAvanceType($type);
        $this->em->persist($value);
        $this->em->flush();

        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue, $params['files_directory']);
      } else {
        return new JsonResponse(['error_message' => 'Une erreur est survenue. (2)'], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Une erreur est survenue. (1)'], 500);
    }
  }

  public function removeConfigurationValuesAvancesDetailAction(Request $request)
  {
    $params = $request->request->all();
    $valueAvance = $this->em->getRepository(MappingConfigurationValueAvanceInterface::class)->find($params['valeur_avancee_id']);
    if ($valueAvance instanceof MappingConfigurationValueAvanceInterface){
      try {
        $configurationValue = $valueAvance->getMappingConfigurationValue();
        $this->em->remove($valueAvance);
        $this->em->flush();
        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue, $params['files_directory']);
      } catch (Exception $e) {
        return new JsonResponse(['error_message' => 'Une erreur est survenue'], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Une erreur est survenue'], 500);
    }
  }

  public function mappingFichierClientSelectChampsAction(Request $request)
  {
    $params = $request->request->all();
    return $this->render(
      '@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs.html.twig',
      [
        'champs' => $this->mapping->getChampsPossiblesAIntegrer($params['mapping_configuration_type']),
        'lib_colonne' => $params['lib_colonne']
      ]
    );
  }

  public function mappingFichierClientSelectChampsOptionsAction(Request $request)
  {
    $params = $request->request->all();
    $champSelect = $this->mapping->getChampPossibleByCode($params['champ'], $params['mapping_configuration_type']);
    if (!is_null($champSelect)) {
      switch ($champSelect['type']) {
        case 'date':

          return $this->render('@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs_options_date.html.twig', ['lib_colonne' => $params['lib_colonne']]);
        default:
          return $this->render('@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs_options_default.html.twig', ['lib_colonne' => $params['lib_colonne']]);
      }
    } else {
      return new Response(['error_message' => 'Une erreur est survenue lors de la sélection du champ. Si le problème persiste, veuillez contacter un administrateur.'], 500);
    }
  }

  public function saveMappingConfigurationAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id']) && isset($params['mapping'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        if ($this->mapping->setMappingConfigurationActive($configuration)) {
          $mappings = $params['mapping'];
          // on supprime les valeurs déjà en BDD
          $values = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy(['mappingConfiguration' => $configuration]);
          foreach ($values as $value) {
            if ($value instanceof MappingConfigurationValueInterface) {
              foreach ($value->getMappingConfigurationValueTranslations() as $translation) {
                $this->em->remove($translation);
              }
            }
            $this->em->remove($value);
          }
          $this->em->flush();

          // on boucle sur toutes les lignes pour les ajouter
          foreach ($mappings as $mapping){
            $className = $this->em->getRepository(MappingConfigurationValueInterface::class)->getClassName();
            $valueTemp = new $className();
            if ($valueTemp instanceof MappingConfigurationValueInterface){
              if (isset($mapping['mapping_code'])) {
                $mapping_code = $mapping['mapping_code'];
              } else {
                $mapping_code = null;
              }
              if (isset($mapping['mapping_type'])) {
                $mapping_type = $mapping['mapping_type'];
              } else {
                $mapping_type = null;
              }
              $valueTemp->setFichierIndex($mapping['index']);
              $valueTemp->setFichierEntete($mapping['nom_entete']);
              $valueTemp->setMappingCode($mapping_code);
              $valueTemp->setMappingType($mapping_type);
              $valueTemp->setMappingConfiguration($configuration);
              $this->em->persist($valueTemp);
            }
          }
          $this->em->flush();
          return new JsonResponse();
        }
        return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration active'], 500);
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration (paramètre manquant)'], 500);
  }

  public function getMappingConfigurationValuesAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])){
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface){
        if ($this->mapping->setMappingConfigurationActive($configuration)){
          $values = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy(['mappingConfiguration' => $configuration]);
          $valuesArray = [];
          foreach ($values as $value) {
            if ($value instanceof MappingConfigurationValueInterface) {
              array_push(
                $valuesArray,
                [
                  'fichier_index' => $value->getFichierIndex(),
                  'fichier_entete' => $value->getFichierEntete(),
                  'mapping_code' => $value->getMappingCode(),
                  'id' => $value->getId(),
                  'mapping_type' => $value->getMappingType(),
                  'mapping_translations' => $value->getMappingCongurationValueTranslationsFormatted(),
                  'mapping_transformations' => $value->getMappingCongurationValueTransformationsFormatted()
                ]
              );
            }
          }
          return new JsonResponse($valuesArray);
        } else {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration active'], 500);
        }
      } else {
        return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (ID non trouvé : '.$params['mapping_id'].')'], 500);
      }
    } else {
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (paramètre manquant)'], 500);
    }
  }

  public function getMappingConfigurationValuesAvancesAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])){
      return $this->mapping->showMappingConfigurationValuesAvances($params['mapping_id']);
    } else {
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (paramètre manquant)'], 500);
    }
  }

  public function addTranslationAction(Request $request, $idValue)
  {
    $params = $request->request->all();
    $mappingValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($idValue);
    if ($mappingValue instanceof MappingConfigurationValueInterface){
      $className = $this->em->getRepository(MappingConfigurationValueTranslationInterface::class)->getClassName();
      $translation = new $className();
      if ($translation instanceof MappingConfigurationValueTranslationInterface){
        $translation->setMappingConfigurationValue($mappingValue);
        $translation->setValue($params['value_fichier']);
        if ($params['translate_mode'] == 'set_traduction') {
          $translation->setTranslation($params['translation']);
        } else {
          $translation->setTranslation(null);
        }
        $this->em->persist($translation);
        $this->em->flush();
        return new JsonResponse(['id' => $translation->getId()]);
      }
    }
    return new JsonResponse([], 500);
  }

  public function removeTranslationAction(Request $request)
  {
    $params = $request->request->all();
    $translation = $this->em->getRepository(MappingConfigurationValueTranslationInterface::class)->find($params['translation_id']);
    if ($translation instanceof MappingConfigurationValueTranslationInterface) {
      try {
        $this->em->remove($translation);
        $this->em->flush();
        return new JsonResponse();
      } catch (Exception $exception) {
        return new JsonResponse([], 500);
      }
    }
    return new JsonResponse([], 500);
  }

  public function updateTranslationsAction(Request $request)
  {
    $params = $request->request->all();
    $value = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($params['mapping_id']);
    if ($value instanceof MappingConfigurationValueInterface){
      return $this->render('@ImanagingCheckFormat/Mapping/mapping_configuration_translations.html.twig', [
        'mapping_value' => $value
      ]);
    } else {
      return new JsonResponse(['error_message' => 'Impossible de trouver la configuration pour l\'id : '.$params['mapping_id'].'.'], 500);
    }
  }

  public function addTransformationAction(Request $request, $idValue)
  {
    $params = $request->request->all();
    $mappingValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($idValue);
    if ($mappingValue instanceof MappingConfigurationValueInterface){
      $className = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)->getClassName();
      $transformationValue = new $className();
      if ($transformationValue instanceof MappingConfigurationValueTransformationInterface){
        $transformationValue->setMappingConfigurationValue($mappingValue);
        $transformationValue->setTransformation($params['transformation']);
        $transformationValue->setNbCaract($params['nb_caractere']);
        $this->em->persist($transformationValue);
        $this->em->flush();
        return new JsonResponse(['id' => $transformationValue->getId()]);
      }
    }
    return new JsonResponse([], 500);
  }

  public function removeTransformationAction(Request $request)
  {
    $params = $request->request->all();
    $transformation = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)->find($params['transformation_id']);
    if ($transformation instanceof MappingConfigurationValueTransformationInterface) {
      try {
        $this->em->remove($transformation);
        $this->em->flush();
        return new JsonResponse();
      } catch (Exception $exception) {
        return new JsonResponse([], 500);
      }
    }
    return new JsonResponse([], 500);
  }

  public function updateTransformationsAction(Request $request)
  {
    $params = $request->request->all();
    $value = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($params['mapping_id']);
    $transformations = TransformationEnum::getAvailableTransformationsWithLibelle();
    if ($value instanceof MappingConfigurationValueInterface) {
      return $this->render('@ImanagingCheckFormat/Mapping/mapping_configuration_transformations.html.twig', [
        'mapping_value' => $value,
        'transformations' => $transformations
      ]);
    } else {
      return new JsonResponse(['error_message' => 'Impossible de trouver la configuration pour l\'id : '.$params['mapping_id'].'.'], 500);
    }
  }

  public function getChampsObligatoiresAMapperAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $res = $this->mapping->getChampsAMapper($params['mapping_id'], true);
      if ($res['error']) {
        return new JsonResponse($res, 500);
      }
      return new JsonResponse($res['champs_a_mapper'], 200);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (paramètre manquant)'], 500);
  }
}