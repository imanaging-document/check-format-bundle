<?php


namespace Imanaging\CheckFormatBundle\Controller;

use App\Entity\MappingConfigurationFile;
use DateTime;
use Exception;
use Imanaging\CheckFormatBundle\Enum\TransformationEnum;
use Imanaging\CheckFormatBundle\Interfaces\MappingChampPossibleInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceDateCustomInterface;
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

  public function indexAction(Request $request)
  {
    $params = $request->request->all();
    $mappingsConfigurationsTypes = [];
    foreach ($params as $param => $value){
      if ($param == $value){
        $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $value]);
        if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
          $mappingsConfigurationsTypes[] = $mappingConfigurationType;
        }
      }
    }
    if (count($mappingsConfigurationsTypes) == 1){
      return $this->redirectToRoute('check_format_mapping_page', ['code' => $mappingsConfigurationsTypes[0]->getCode()]);
    }

    return $this->render("@ImanagingCheckFormat/Mapping/index.html.twig", [
      'mappings_configurations_types' => $mappingsConfigurationsTypes,
      'basePath' => $params['basePath']
    ]);
  }

  public function mappingPageAction($code)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $directory = $this->projectDir . $mappingConfigurationType->getFilesDirectory() . $mappingConfigurationType->getFilename() . '*';
      $fichiersClient = glob($directory);
      if (count($fichiersClient) == 1) {
        $data = $this->mapping->getFirstLinesFromFile($fichiersClient[0], 10);
        $ligneEntete = $data['entete'];
        $lignes = $data['first_lines'];

        return $this->render("@ImanagingCheckFormat/Mapping/mapping_page.html.twig", [
          'basePath' => 'base.html.twig',
          'champs' => $this->mapping->getChampsPossiblesAIntegrer($code),
          'ligne_entete' => $ligneEntete,
          'lignes' => $lignes,
          'fichiers_en_attente' => $fichiersClient,
          'mapping_configuration_type' => $mappingConfigurationType
        ]);
      } else {
        $champsObligatoires = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy(['mappingConfigurationType' => $mappingConfigurationType, 'visible' => true, 'obligatoire' => true]);
        return $this->render("@ImanagingCheckFormat/Mapping/no_fichier_to_map.html.twig", [
          'basePath' => 'base.html.twig',
          'mapping_configuration_type' => $mappingConfigurationType,
          'champs_obligatoires' => $champsObligatoires
        ]);
      }
    } else {
      return $this->render("@ImanagingCheckFormat/Mapping/mapping_configuration_type_not_found.html.twig", [
        'code' => $code,
        'basePath' => 'base.html.twig',
      ]);
    }
  }

  public function uploadFileAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $dir = $this->projectDir.$mappingConfigurationType->getFilesDirectory();
      if (!is_dir($dir)){
        mkdir($dir, 0775, true);
      }

      $files = $request->files->all();
      $fichier = $files['file'];
      if ($fichier instanceof UploadedFile){
        try {
          $now = new DateTime();
          $newFileName = $mappingConfigurationType->getFilename().'_'.$now->format('YmdHis').'.'.$fichier->getClientOriginalExtension();

          $className = $this->em->getRepository(MappingConfigurationFileInterface::class)->getClassName();
          $mappingFile = new $className();
          if ($mappingFile instanceof MappingConfigurationFileInterface) {
            $mappingFile->setMappingConfigurationType($mappingConfigurationType);
            $mappingFile->setDateImport($now);
            $mappingFile->setInitialFilename($fichier->getClientOriginalName());
            $mappingFile->setFilename($newFileName);
            $this->em->persist($mappingFile);
            $this->em->flush();

            $fichier->move($dir, $newFileName);
            if (file_exists($dir.$newFileName)){
              chmod($dir.$newFileName, 0755);
              return new JsonResponse();
            } else {
              return new JsonResponse(["error_message" => "Une erreur est survenue lors de l\'envoi du fichier :( #2"], 500);
            }
          } else {
            return new JsonResponse(["error_message" => "Une erreur est survenue lors de l\'envoi du fichier :("], 500);
          }
        } catch (Exception $e){
          return new JsonResponse(["error_message" => "Une erreur est survenue lors de l\'envoi du fichier :( ". $e->getMessage()], 500);
        }
      } else {
        return new JsonResponse(["error_message" => "Veuillez soumettre un fichier valide."], 500);
      }
    } else {
      return $this->redirectToRoute('check_format_mapping_page', ['code' => $code]);
    }
  }

  public function deleteFileAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $params = $request->request->all();
      $dir = $this->projectDir.$mappingConfigurationType->getFilesDirectory();
      $mappingConfigurationFile = $this->em->getRepository(MappingConfigurationFileInterface::class)->findOneBy(['filename' => $params['filename'], 'mappingConfigurationType' => $mappingConfigurationType]);
      if ($mappingConfigurationFile instanceof MappingConfigurationFileInterface) {
        if (is_dir($dir)){
          if (file_exists($dir.'/'.$params['filename'])){
            try{
              unlink($dir.'/'.$params['filename']);

              $this->em->remove($mappingConfigurationFile);
              $this->em->flush();
              return new JsonResponse();
            } catch (Exception $e){
              return new JsonResponse([], 500);
            }
          }
        }
      }
    }
    return new JsonResponse([], 500);
  }

  public function controlePageAction($code)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $fichiersClients = array();
      foreach (glob($this->projectDir . $mappingConfigurationType->getFilesDirectory() . $mappingConfigurationType->getFilename() . '*') as $path) {
        $fichiersClients[] = basename($path);
      }
      return $this->render("@ImanagingCheckFormat/Mapping/controle.html.twig", [
        'mapping_configuration_type' => $mappingConfigurationType,
        'basePath' => 'base.html.twig',
        "fichiers_clients" => $fichiersClients
      ]);
    } else {
      return $this->render("@ImanagingCheckFormat/Mapping/mapping_configuration_type_not_found.html.twig", [
        'code' => $code,
        'basePath' => 'base.html.twig',
      ]);
    }
  }

  public function controlerFichierAction($code)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $result = $this->mapping->controlerFichiers($mappingConfigurationType, true);
      if ($result['error']) {
        return $this->render('@ImanagingCheckFormat/Mapping/controle/controle_ko.html.twig', [
          'mapping_configuration_type' => $mappingConfigurationType,
          'resultat' => $result
        ]);
      } else {
        return $this->render('@ImanagingCheckFormat/Mapping/controle/controle_ok.html.twig', [
          'mapping_configuration_type' => $mappingConfigurationType,
          'resultat' => $result
        ]);
      }
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function gererChampsPossiblesAction($code)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
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

  public function modelEditChampPossibleAction(Request $request)
  {
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

  public function saveChampPossibleAction(Request $request)
  {
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

  public function toggleBooleanValueChampPossibleAction(Request $request)
  {
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

  public function getMappingConfigurationSelectAction($codeMappingConfiguration)
  {
    $mappingTypeConfiguration = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $codeMappingConfiguration]);
    if ($mappingTypeConfiguration instanceof MappingConfigurationTypeInterface){
      $mappingConfigurations = $this->em->getRepository(MappingConfigurationInterface::class)->findBy(['type' => $mappingTypeConfiguration]);
      return $this->render('@ImanagingCheckFormat/Mapping/partials/mapping_configuration_select.html.twig', [
        'mapping_configuration_type' => $mappingTypeConfiguration,
        'mapping_configurations' => $mappingConfigurations,
      ]);
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function addMappingConfigurationAction($code, Request $request)
  {
    $params = $request->request->all();
    $em = $this->getDoctrine()->getManager();
    if (isset($params['libelle'])) {
      $libelle = $params['libelle'];
      $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
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
        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue);
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
        } elseif ($type->getCode() == 'date_custom') {
          $className = $this->em->getRepository(MappingConfigurationValueAvanceDateCustomInterface::class)->getClassName();
          $value = new $className();
          if ($value instanceof MappingConfigurationValueAvanceDateCustomInterface){
            $value->setFormat($params['date_custom_format']);
            $value->setModifier($params['date_custom_modify']);
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

        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue);
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
        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue);
      } catch (Exception $e) {
        return new JsonResponse(['error_message' => 'Une erreur est survenue'], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Une erreur est survenue'], 500);
    }
  }

  public function mappingFichierClientSelectChampsAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $params = $request->request->all();
      return $this->render(
        '@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs.html.twig',
        [
          'mapping_configuration_type' => $mappingConfigurationType,
          'champs' => $this->mapping->getChampsPossiblesAIntegrer($mappingConfigurationType->getCode()),
          'lib_colonne' => $params['lib_colonne']
        ]
      );
    } else {
      return new JsonResponse(['error_message' => 'Une erreur est survenue'], 500);
    }
  }

  public function mappingFichierClientSelectChampsOptionsAction($code, Request $request)
  {
    $params = $request->request->all();
    $champSelect = $this->mapping->getChampPossibleByCode($params['champ'], $code);
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
    $translationsArr = [];
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
                if ($translation instanceof MappingConfigurationValueTranslationInterface) {
                  $translationsArr[] = [
                    'index_fichier' => $translation->getMappingConfigurationValue()->getFichierIndex(),
                    'mapping_code' => $translation->getMappingConfigurationValue()->getMappingCode(),
                    'value' => $translation->getValue(),
                    'translation' => $translation->getTranslation(),
                  ];
                  $this->em->remove($translation);
                }
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
              if (isset($mapping['mapping_code']) && $mapping['mapping_code'] != '') {
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

              // on cherche si une translation correspond pour la remettre
              foreach ($translationsArr as $translation) {
                if ($translation['index_fichier'] == $mapping['index'] && $translation['mapping_code'] == $mapping_code) {
                  $classTranslationName = $this->em->getRepository(MappingConfigurationValueTranslationInterface::class)->getClassName();
                  $translationValue = new $classTranslationName();
                  if ($translationValue instanceof MappingConfigurationValueTranslationInterface){
                    $translationValue->setMappingConfigurationValue($valueTemp);
                    $translationValue->setTranslation($translation['translation']);
                    $translationValue->setValue($translation['value']);
                    $this->em->persist($translationValue);
                  }
                }
              }
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

  public function showRecapMappingConfigurationAction(Request $request) {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        return $this->render('@ImanagingCheckFormat/Mapping/mapping_configuration_recapitulatif.html.twig', [
          'config' => $configuration
        ]);
      }
    }
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