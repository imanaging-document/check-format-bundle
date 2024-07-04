<?php


namespace Imanaging\CheckFormatBundle\Controller;

use App\Entity\MappingConfiguration;
use App\Entity\MappingConfigurationFile;
use App\Entity\MappingConfigurationValue;
use App\Entity\MappingConfigurationValueTransformation;
use DateTime;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceAutoIncrementInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceSaisieManuelleInterface;
use Symfony\Component\HttpFoundation\Response;
use Exception;
use Imanaging\CheckFormatBundle\Enum\TransformationEnum;
use Imanaging\CheckFormatBundle\Interfaces\MappingChampPossibleInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceDateCustomInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceMultiColumnArrayInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTextInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTransformationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTranslationInterface;
use Imanaging\CheckFormatBundle\Mapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

class MappingController extends AbstractController
{
  private $em;
  private $mapping;
  private $projectDir;
  private $twig;

  /**
   * MappingController constructor.
   * @param EntityManagerInterface $em
   * @param Mapping $mapping
   * @param $projectDir
   */
  public function __construct(EntityManagerInterface $em, Mapping $mapping, Environment $twig)
  {
    $this->em = $em;
    $this->mapping = $mapping;
    $this->projectDir = $mapping->getProjectDir();
    $this->twig = $twig;
  }

  public function indexAction(Request $request)
  {
    $params = $request->request->all();
    $mappingsConfigurationsTypes = [];
    $othersParam = '';
    foreach ($params as $param => $value){
      if ($param == $value){
        $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $value]);
        if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
          $mappingsConfigurationsTypes[] = $mappingConfigurationType;
        }
      } elseif ($param != 'basePath') {
        if ($othersParam == '') {
          $othersParam = '?' . $param . '=' . $value;
        } else {
          $othersParam = '&' . $param . '=' . $value;
        }
      }
    }
    if (count($mappingsConfigurationsTypes) == 1){
      return $this->redirect($this->generateUrl('check_format_mapping_page', ['code' => $mappingsConfigurationsTypes[0]->getCode()]).$othersParam);
    }

    return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/index.html.twig", [
      'mappings_configurations_types' => $mappingsConfigurationsTypes,
      'basePath' => $params['basePath']
    ]));
  }

  public function mappingPageAction($code)
  {
    ini_set('memory_limit', '512M');
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $directory = $this->projectDir . $mappingConfigurationType->getFilesDirectory() . $mappingConfigurationType->getFilename() . '*';
      $fichiersClient = glob($directory);
      if (count($fichiersClient) == 1) {
        $fichiersClientFormatted = [];
        foreach ($fichiersClient as $fichier) {
          $fichiersClientFormatted[] = ['filename' => $fichier, 'basename' => basename($fichier)];
        }
        $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['active' => true, 'type' => $mappingConfigurationType]);
        if ($mappingConfiguration instanceof MappingConfiguration) {
          $cuttingRules = $mappingConfiguration->getMappingConfigurationCuttingRules();
          $skippingRules = $mappingConfiguration->getMappingConfigurationSkippingRules();
        } else {
          $cuttingRules = [];
          $skippingRules = [];
        }

        $data = $this->mapping->getFirstLinesFromFile($fichiersClient[0], 10, $cuttingRules, $skippingRules);
        $ligneEntete = $data['entete'];
        $lignes = $data['first_lines'];

        return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/mapping_page.html.twig", [
          'basePath' => 'base.html.twig',
          'champs' => $this->mapping->getChampsPossiblesAIntegrer($code),
          'ligne_entete' => $ligneEntete,
          'lignes' => $lignes,
          'fichiers_en_attente' => $fichiersClientFormatted,
          'mapping_configuration_type' => $mappingConfigurationType
        ]));
      } else {
        $champsObligatoires = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy(['mappingConfigurationType' => $mappingConfigurationType, 'visible' => true, 'obligatoire' => true]);
        return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/no_fichier_to_map.html.twig", [
          'basePath' => 'base.html.twig',
          'mapping_configuration_type' => $mappingConfigurationType,
          'champs_obligatoires' => $champsObligatoires
        ]));
      }
    } else {
      return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/mapping_configuration_type_not_found.html.twig", [
        'code' => $code,
        'basePath' => 'base.html.twig',
      ]));
    }
  }

  public function uploadFileAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['active' => true, 'type' => $mappingConfigurationType]);
      if (!($mappingConfiguration instanceof MappingConfigurationInterface)) {
        $className = $this->em->getRepository(MappingConfigurationInterface::class)->getClassName();
        $mappingConfiguration = new $className();
        $mappingConfiguration->setType($mappingConfigurationType);
        $mappingConfiguration->setLibelle($mappingConfigurationType->getLibelle());
        $mappingConfiguration->setActive(true);
        $this->em->persist($mappingConfiguration);
      }
      $dir = $this->projectDir.$mappingConfigurationType->getFilesDirectory();
      if (!is_dir($dir)){
        mkdir($dir, 0775, true);
      }

      $files = $request->files->all();
      if (isset($files['file'])){
        $fichier = $files['file'];
        if ($fichier instanceof UploadedFile){

          try {
            $now = new DateTime();
            $newFileName = $mappingConfigurationType->getFilename().'_'.$now->format('YmdHis').'.'.$fichier->getClientOriginalExtension();

            $className = $this->em->getRepository(MappingConfigurationFileInterface::class)->getClassName();
            $mappingFile = new $className();
            if ($mappingFile instanceof MappingConfigurationFileInterface) {
              $mappingFile->setMappingConfiguration($mappingConfiguration);
              $mappingFile->setDateImport($now);
              $mappingFile->setInitialFilename($fichier->getClientOriginalName());
              $mappingFile->setFilename($newFileName);

              $params = $request->request->all();
              if (count($params) > 0){
                $mappingFile->setFormData(json_encode($params));
              }

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
        return new JsonResponse(["error_message" => "Veuillez soumettre un fichier."], 500);
      }
    } else {
      return new JsonResponse(["error_message" => "Type de configuration introuvable :".$code], 500);
    }
  }

  public function deleteFileAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $params = $request->request->all();
      $dir = $this->projectDir.$mappingConfigurationType->getFilesDirectory();
      foreach ($mappingConfigurationType->getMappingConfigurations() as $mappingConfiguration) {
        if ($mappingConfiguration instanceof MappingConfigurationInterface) {
          $mappingConfigurationFile = $this->em->getRepository(MappingConfigurationFileInterface::class)->findOneBy(['filename' => $params['filename'], 'mappingConfiguration' => $mappingConfiguration]);
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
      }

      if (is_dir($dir)){
        if (file_exists($dir.'/'.$params['filename'])) {
          try {
            unlink($dir . '/' . $params['filename']);
          } catch (Exception $e){
            return new JsonResponse([], 500);
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
      $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['active' => true, 'type' => $mappingConfigurationType]);
      if ($mappingConfiguration instanceof MappingConfigurationInterface) {
        $valuesSaisiesManuelles = $this->mapping->getValueAvancesSaisieManuelleConfigurationMappingImport($mappingConfiguration);
        $fichiersClients = array();
        foreach (glob($this->projectDir . $mappingConfigurationType->getFilesDirectory() . $mappingConfigurationType->getFilename() . '*') as $path) {
          $fichiersClients[] = $this->em->getRepository(MappingConfigurationFileInterface::class)->findOneBy([
            'filename' =>  basename($path),
            'mappingConfiguration' => $mappingConfiguration
          ]);
        }
        return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/controle.html.twig", [
          'mapping_configuration_type' => $mappingConfigurationType,
          'basePath' => 'base.html.twig',
          "fichiers_clients" => $fichiersClients,
          'advanced_values_saisie_manuelle' => $valuesSaisiesManuelles
        ]));
      } else {
        return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/mapping_configuration_type_not_found.html.twig", [
          'code' => $code,
          'basePath' => 'base.html.twig',
        ]));
      }
    } else {
      return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/mapping_configuration_type_not_found.html.twig", [
        'code' => $code,
        'basePath' => 'base.html.twig',
      ]));
    }
  }

  public function controlerFichierAction($code)
  {
    ini_set('memory_limit', '512M');
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $mappingConfiguration = $this->getMappingConfigurationActive($mappingConfigurationType);
      if ($mappingConfiguration instanceof MappingConfigurationInterface) {
        $result = $this->mapping->controlerFichiers($mappingConfiguration, true);
        if ($result['error']) {
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/controle/controle_ko.html.twig', [
            'mapping_configuration_type' => $mappingConfigurationType,
            'resultat' => $result
          ]));
        } else {
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/controle/controle_ok.html.twig', [
            'mapping_configuration_type' => $mappingConfigurationType,
            'resultat' => $result
          ]));
        }
      } else {
        return new JsonResponse([], 500);
      }
    } else {
      return new JsonResponse([], 500);
    }
  }

  private function getMappingConfigurationActive(MappingConfigurationTypeInterface $mappingConfigurationType) {
    $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['active' => true, 'type' => $mappingConfigurationType]);
    return $mappingConfiguration;
  }

  public function gererChampsPossiblesAction($code)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $champsPossibles = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy(['mappingConfigurationType' => $mappingConfigurationType, 'visible' => true]);
      return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/gerer_champs_possibles.html.twig", [
        'mapping_configuration_type' => $mappingConfigurationType,
        'champsPossibles' => $champsPossibles,
      ]));
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function modelEditChampPossibleAction(Request $request)
  {
    $params = $request->request->all();
    $champPossible = $this->em->getRepository(MappingChampPossibleInterface::class)->find($params['champ_id']);
    if ($champPossible instanceof MappingChampPossibleInterface){
      return new Response($this->twig->render("@ImanagingCheckFormat/Mapping/modals/edit_champ_possible.html.twig", [
        'champPossible' => $champPossible
      ]));
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
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/partials/mapping_configuration_select.html.twig', [
        'mapping_configuration_type' => $mappingTypeConfiguration,
        'mapping_configurations' => $mappingConfigurations,
      ]));
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function addMappingConfigurationAction($code, Request $request)
  {
    $params = $request->request->all();
    $files = $request->files->all();
    if (isset($params['mapping_configuration_libelle'])) {
      $libelle = $params['mapping_configuration_libelle'];
      $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
      if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
        $className = $this->em->getRepository(MappingConfigurationInterface::class)->getClassName();
        $configuration = new $className();
        if ($configuration instanceof MappingConfigurationInterface){
          $configuration->setType($mappingConfigurationType);
          $configuration->setLibelle($libelle);
          $configuration->setActive(true);

          $champsPossibles = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy(['mappingConfigurationType' => $mappingConfigurationType]);
          $champsPossiblesCodes = [];
          foreach ($champsPossibles as $champPossible) {
            if ($champPossible instanceof MappingChampPossibleInterface) {
              $champsPossiblesCodes[] = $champPossible->getData();
            }
          }

          if (isset($files['file_import'])) {
            // mode importation
            $data = Yaml::parseFile($files['file_import']->getPathname());
            foreach ($data['values'] as $_value) {
              if (!array_key_exists($_value['mapping_code'], $champsPossiblesCodes)) {
                return new JsonResponse([
                  'error' => true,
                  'error_message' => 'Une champs n\'existe pas, avez-vous ajouté les champs complémentaires ?'
                ], 500);
              }
              $className = $this->em->getRepository(MappingConfigurationValueInterface::class)->getClassName();
              $value = new $className();
              $value->setMappingConfiguration($configuration);
              $value->setFichierEntete($_value['entete']);
              $value->setFichierIndex($_value['fichier_index']);
              $value->setMappingCode($_value['mapping_code']);
              $value->setMappingType($_value['type_mapping']);
              $this->em->persist($value);


              foreach ($_value['transformations'] as $_transformation) {
                $className = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)->getClassName();
                $transformation = new $className();
                $transformation->setMappingConfigurationValue($value);
                $transformation->setNbCaract($_transformation['nb_caract']);
                $transformation->setTransformation($_transformation['transformation']);
                $this->em->persist($transformation);
              }
              foreach ($_value['translations'] as $_translation) {
                $className = $this->em->getRepository(MappingConfigurationValueTranslationInterface::class)->getClassName();
                $translation = new $className();
                $translation->setMappingConfigurationValue($value);
                $translation->setValue($_translation['value']);
                $translation->setTranslation($_translation['translation']);
                $this->em->persist($translation);
              }
              foreach ($_value['values_avances'] as $_valueAvance) {
                $type = $this->em->getRepository(MappingConfigurationValueAvanceTypeInterface::class)->findOneBy(['code' => $_valueAvance['type']]);
                if ($type instanceof MappingConfigurationValueAvanceTypeInterface){
                  // on ajoute la value avancée
                  if ($type->getCode() == 'value_file') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceFileInterface::class)->getClassName();
                  } elseif ($type->getCode() == 'date_custom') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceDateCustomInterface::class)->getClassName();
                  } elseif ($type->getCode() == 'multi_column_array') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceMultiColumnArrayInterface::class)->getClassName();
                  } elseif ($type->getCode() == 'auto_increment') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceAutoIncrementInterface::class)->getClassName();
                  } elseif ($type->getCode() == 'saisie_manuelle') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceSaisieManuelleInterface::class)->getClassName();
                  } else {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceTextInterface::class)->getClassName();
                  }
                  $valueAvance = new $className();
                  if ($valueAvance instanceof MappingConfigurationValueAvanceInterface) {
                    $valueAvance->initFromImportFileValueAvance($_valueAvance);
                    $valueAvance->setMappingConfigurationValueAvanceType($type);
                    $valueAvance->setMappingConfigurationValue($value);
                    $valueAvance->setOrdre($_valueAvance['order']);
                  }

                  $this->em->persist($valueAvance);
                } else {
                  return new JsonResponse(['error_message' => 'Une erreur est survenue. (2)'], 500);
                }
              }
            }
          }
          $this->em->persist($configuration);
          $this->em->flush();
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

  public function exportMappingConfigurationAction(Request $request) {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        $publicDir = $this->projectDir.'/public';
        $tmpDir = $publicDir.'/database/tmp_files';
        if (!is_dir($tmpDir)) {
          mkdir($tmpDir, '0755', true);
        }
        $filename = 'tmp_export_mapping_configuration_'.$configuration->getId();
        $filePath = $tmpDir.'/' . $filename . '.yaml';
        if (file_exists($filePath)) {
          unlink($filePath);
        }

        $dumpedData = Yaml::dump($configuration->getFormattedConfiguration(), 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($filePath, $dumpedData);

        return new JsonResponse(['success' => true, 'url' => $this->generateUrl('check_format_mapping_download_export_configuration', ['filename' => $filename ])]);
      } else {
        return new JsonResponse(['error_message' => 'La configuration n\'a pas été trouvé'], 500);
      }
    }
    return new JsonResponse(['error_message' => 'Un paramètre est manquant pour exporter la configuration.'], 500);
  }

  public function downloadExportMappingConfigurationAction($filename) {
    $filepath = $this->projectDir.'/public/database/tmp_files/'.$filename.'.yaml';
    if (file_exists($filepath)) {
      return $this->file($filepath);
    }
    throw new Exception('File not found');
  }

  public function importMappingConfigurationAction(Request $request) {
    $params = $request->request->all();
    $files = $request->files->all();
    var_dump($params);
    var_dump($files);
    die;
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
        } elseif ($type->getCode() == 'multi_column_array') {
          $className = $this->em->getRepository(MappingConfigurationValueAvanceMultiColumnArrayInterface::class)->getClassName();
          $value = new $className();
          if ($value instanceof MappingConfigurationValueAvanceMultiColumnArrayInterface){
            $value->setDelimiter($params['delimiter']);
            $value->setColumns($params['multi_column_array']);
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
        $value->setOrdre(count($configurationValue->getMappingConfigurationValueAvances()) + 1);
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
      return new Response($this->twig->render(
        '@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs.html.twig',
        [
          'mapping_configuration_type' => $mappingConfigurationType,
          'champs' => $this->mapping->getChampsPossiblesAIntegrer($mappingConfigurationType->getCode()),
          'lib_colonne' => $params['lib_colonne']
        ]
      ));
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
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs_options_date.html.twig', ['lib_colonne' => $params['lib_colonne']]));
        case 'array':
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs_options_array.html.twig', ['lib_colonne' => $params['lib_colonne']]));
        default:
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs_options_default.html.twig', ['lib_colonne' => $params['lib_colonne']]));
      }
    } else {
      return new Response(['error_message' => 'Une erreur est survenue lors de la sélection du champ. Si le problème persiste, veuillez contacter un administrateur.'], 500);
    }
  }

  public function saveMappingConfigurationAction(Request $request)
  {
    $valuesToDelete = [];
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
              if (!is_null($value->getFichierIndex())) {
                $valuesToDelete[$value->getId()] = $value;
              }
            }
          }

          // on boucle sur toutes les lignes pour les ajouter
          foreach ($mappings as $mapping){
            // on recherche la ligne
            $configurationValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->findOneBy(['mappingConfiguration' => $configuration, 'fichierIndex' => $mapping['index']]);
            if ($configurationValue instanceof MappingConfigurationValueInterface) {
              unset($valuesToDelete[$configurationValue->getId()]); // on enlève de la liste à supprimer
            } else {
              $className = $this->em->getRepository(MappingConfigurationValueInterface::class)->getClassName();
              $configurationValue = new $className();
              if ($configurationValue instanceof MappingConfigurationValueInterface){
                $configurationValue->setFichierIndex($mapping['index']);
                $configurationValue->setMappingConfiguration($configuration);
              }
            }

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
            $configurationValue->setFichierEntete($mapping['nom_entete']);
            $configurationValue->setMappingCode($mapping_code);
            $configurationValue->setMappingType($mapping_type);
            $this->em->persist($configurationValue);
          }

          // on supprimes les valeurs non retrouvées
          foreach ($valuesToDelete as $id => $valueToDelete) {
            if ($valueToDelete instanceof MappingConfigurationValueInterface) {
              foreach ($valueToDelete->getMappingConfigurationValueTranslations() as $translation) {
                $this->em->remove($translation);
              }
              foreach ($valueToDelete->getMappingConfigurationValueTransformations() as $transformation) {
                $this->em->remove($transformation);
              }
              $this->em->remove($valueToDelete);
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
        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_recapitulatif.html.twig', [
          'config' => $configuration
        ]));
      }
    }
    return new JsonResponse([], 500);
  }

  public function showRecapDecoupageChampsMappingConfigurationAction(Request $request) {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_show_cutting_rules.html.twig', [
          'config' => $configuration
        ]));
      }
    }
    return new JsonResponse([], 500);
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
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_translations.html.twig', [
        'mapping_value' => $value
      ]));
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
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_transformations.html.twig', [
        'mapping_value' => $value,
        'transformations' => $transformations
      ]));
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

  public function saveSaisiesManuellesConfigurationFileAction($id, Request $request) {
    $mappingFile = $this->em->getRepository(MappingConfigurationFileInterface::class)->find($id);
    if ($mappingFile instanceof MappingConfigurationFileInterface) {
      $params = $request->request->all();
      foreach ($params as $id => $value) {
        $mappingValueAvance = $this->em->getRepository(MappingConfigurationValueAvanceSaisieManuelleInterface::class)->find($id);
        if ($mappingValueAvance instanceof MappingConfigurationValueAvanceSaisieManuelleInterface) {
          $mappingFile->setValueSaisieManuelle($mappingValueAvance->getId(), $value);
        }
      }
      $this->em->persist($mappingFile);
      $this->em->flush();

      return $this->redirectToRoute('check_format_mapping_controle_page', ['code' => $mappingFile->getMappingConfiguration()->getType()->getCode()]);
    } else {
      throw new Exception('Mapping file introuvable');
    }
  }
}
