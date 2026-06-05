require('bootstrap');

$( document ).ready( function () {
  if ($('#mapping_configuration').length > 0) {
    loadMappingConfigurations();
  }
});

let requestPending = false;


$(document).on('click', '.delete-file', function(){
  let filename = $(this).data('file-basename');
  let url = $(this).data('url');
  let data = {filename: filename};
  $.ajax({
    url: url,
    type: 'POST',
    data: data,
    success: function(){
      location.reload();
    },
    error: function(){
      alert('Une erreur est survenue lors de la suppression');
    }
  });
});

$("#btnGererChampsPossibles").click(function(){
  $.ajax({
    url: $(this).data('url'),
    type: 'GET',
    success: function(data){
      $("#gerer-champs-possibles-card").html(data).removeClass('d-none');
      document.getElementById('gerer-champs-possibles-card').scrollIntoView();
    },
    error: function(){
      alert("Une erreur est survenue lors du chargement des champs possibles");
    }
  });
});

function initAllBtn(){
  console.log('init btn');
  $('#divRecapitulatif').addClass('d-none');
  $('#divModeAvance').addClass('d-none');
  $('#divMappingMode').addClass('d-none');
  $('#divModeDecoupageChamps').addClass('d-none');
  $('#btnModeRecapitulaif').addClass('btn-outline-secondary').removeClass('btn-success btn-raised');
  $('#btnModeDecoupageChamps').addClass('btn-outline-secondary').removeClass('btn-primary btn-raised');
  $('#btnModeMapping').addClass('btn-outline-secondary').removeClass('btn-warning btn-raised');
  $('#btnModeAvance').addClass('btn-outline-secondary').removeClass('btn-info btn-raised');
}

$('#btnModeRecapitulaif').click(function() {
  initAllBtn();
  $('#divRecapitulatif').removeClass('d-none');
  $('#btnModeRecapitulaif').addClass('btn-success btn-raised').removeClass('btn-outline-secondary');
});

$('#btnModeDecoupageChamps').click(function() {
  initAllBtn();
  $('#divModeDecoupageChamps').removeClass('d-none');
  $('#btnModeDecoupageChamps').removeClass('btn-outline-secondary').addClass('btn-primary btn-raised');
});

$('#btnModeMapping').click(function() {
  initAllBtn();
  $('#divMappingMode').removeClass('d-none');
  $('#btnModeMapping').removeClass('btn-outline-secondary').addClass('btn-warning btn-raised');
});

$('#btnModeAvance').click(function() {
  initAllBtn();
  $('#divModeAvance').removeClass('d-none');
  $('#btnModeAvance').removeClass('btn-outline-secondary').addClass('btn-info btn-raised');
});

var mappingId = "";

$('.mapping_data_value').click(function() {
  if (!requestPending) {
    let data = {lib_colonne: $(this).data('lib-colonne')};
    requestPending = true;
    $.ajax({
      url: $('#tr_mapping').data('url-select-champs'),
      type: 'POST',
      data: data,
      success: function (data) {
        $("#divModalSelectChamps").html(data);
        $("#modalSelectChamps").modal();
        requestPending = false;
      },
      error: function () {
        alert('Une erreur est survenue lors de la sélection du champs. Veuillez réessayer plus tard.');
        requestPending = false;
      }
    });
  }
});

function saveMapping() {
  var mappingConfiguration = $('#mapping').val();
  if (mappingConfiguration !== null && mappingConfiguration !== undefined) {
    var dataMapping = [];
    $('#tr_mapping th').each(function() {
      var thTemp = $(this);
      var thTemp = $(this);
      dataMapping.push(
          {

            index: thTemp.data('index'),
            nom_entete: thTemp.data('lib-colonne'),
            mapping_code: thTemp.data('code-mapping'),
            mapping_type: thTemp.data('type-mapping')
          }
      );
    });

    var data = {mapping_id: mappingConfiguration, mapping: dataMapping};
    $.ajax({
      url: $("#mapping").data('url'),
      type: 'POST',
      data: data,
      success: function (){
        updateRecapitulatif();
      },
      error: function (){
        alert('Une erreur est survenue lors de l\'enregistrement de laconfiguration. Veuillez réessayer plus tard.');
      }
    });
  } else {
    alert('Erreur lors de l\'enregistrement du mapping. Aucune configuration n\'a été sélectionnée.');
  }
}

function loadMappingConfigurations() {
  var loader = $('#mapping_configuration_loader');
  if (loader.hasClass('d-none')) {
    loader.removeClass('d-none');
    $('#mapping_configuration').html('');
  }
  $.ajax({
    url: $('#mapping_configuration').data('url'),
    type: 'GET',
    success: function(data){
      loader.addClass('d-none');
      $('#mapping_configuration').html(data);
      loadConfiguration();
    },
    error: function(){
      alert('Une erreur est survenue lors du chargement des configurations. Veuillez réessayer plus tard.');
    }
  });
}

function loadModeDecoupageChamps() {
  console.log("Load découpage champs")
  var mappingConfiguration = $('#mapping').val();
  if (mappingConfiguration !== null) {
    $('#tableModeDecoupageChamp').html('on est la');
    $.ajax({
      url: $('#tableModeDecoupageChamp').data('url'),
      type: 'POST',
      data: {mapping_id: mappingConfiguration},
      success: function (data) {
        $('#tableModeDecoupageChamp').html(data);
      },
      error: function () {
        showSweetAlert('top-end', 'error', 'Une erreur est survenue lors de la récupération de la configuration. Veuillez réessayer plus tard.', true, 3000);
      }
    });
  } else {
    $('#tableModeDecoupageChamp').html('Configuration introuvable');
  }
}

function loadConfiguration() {
  if ($('#xml-source-configuration').length > 0) {
    loadXmlSourceConfiguration();
    loadXmlFieldMappings();
    buildXmlWizardSummary();
    return;
  }

  $('#mappingConfigurationLoad').removeClass('d-none');
  $('#affichageMappingGlobal').addClass('d-none');
  var mappingConfiguration = $('#mapping').val();
  if (mappingConfiguration !== null) {
    // on vide la configuration actuelle
    $('#tr_mapping th').each(function() {
      $(this).text("");
      $(this).removeData('code-mapping');
      $(this).removeData('type-mapping');
      $(this).removeData('entete-fichier');
      $(this).removeClass("mapping_data_value_ok");
    });

    $.ajax({
      url: $('#tr_mapping').data('url'),
      type: 'POST',
      data: {mapping_id: mappingConfiguration},
      success: function (data) {
        data.forEach(function(el) {
          var th = $('tr#tr_mapping th[data-index="' + el.fichier_index + '"]');
          if (el.mapping_code != null) {
            th.text(el.mapping_code);
            th.data('id-mapping', el.id);
            th.data('code-mapping', el.mapping_code);
            th.data('entete-fichier', el.fichier_entete);
            th.data('translations', el.mapping_translations);
            th.data('transformations', el.mapping_transformations);
            if (el.mapping_type != null) {
              th.data('type-mapping', el.mapping_type);
            }
            th.addClass("mapping_data_value_ok")
          }
        });
        updateRecapitulatif();
        saveMapping();
      },
      error: function () {
        showSweetAlert('top-end', 'error', 'Une erreur est survenue lors de la récupération de la configuration. Veuillez réessayer plus tard.', true, 3000);
      },
      complete: function() {
        $('#mappingConfigurationLoad').addClass('d-none');
        $('#affichageMappingGlobal').removeClass('d-none');
      }
    });

    $.ajax({
      url: $('#divModeAvance').data('url'),
      type: 'POST',
      data: {mapping_id: mappingConfiguration},
      success: function (data) {
        $('#divModeAvance').html(data);
      },
      error: function () {
        showSweetAlert('top-end', 'error', 'Une erreur est survenue lors de la récupération de la configuration. Veuillez réessayer plus tard.', true, 3000);
      }
    });
  } else {
    $("#mappingConfigurationLoad").html('<p>Il n\'existe aucune configuration de mapping pour ce type. ' +
        'Créer votre première configuration en cliquant sur <b>Ajouter une nouvelle configuration</b>.</h4>');
  }
}

function getSelectedMappingSourceOptions() {
  var selectedOption = $('#mapping option:selected');
  var sourceOptions = selectedOption.data('source-options');
  if (typeof sourceOptions === 'string' && sourceOptions !== '') {
    try {
      sourceOptions = JSON.parse(sourceOptions);
    } catch (e) {
      sourceOptions = {};
    }
  }
  if (sourceOptions === null || typeof sourceOptions !== 'object') {
    sourceOptions = {};
  }
  return sourceOptions;
}

function loadXmlSourceConfiguration() {
  var sourceOptions = getSelectedMappingSourceOptions();
  var rowXpath = sourceOptions.row_xpath || '';
  $('#xml_row_xpath').val(rowXpath);
  if (rowXpath !== '') {
    $('#xml-source-configuration-saved').removeClass('d-none');
  } else {
    $('#xml-source-configuration-saved').addClass('d-none');
  }
  buildXmlWizardSummary();
}

$(document).on('click', '.btn-use-xml-row-xpath', function() {
  $('#xml_row_xpath').val($(this).data('row-xpath'));
  $('#xml-source-configuration-saved').addClass('d-none');
});

$(document).on('click', '#btn-save-xml-source-configuration', function() {
  var mappingConfiguration = $('#mapping').val();
  var rowXpath = $('#xml_row_xpath').val();
  if (mappingConfiguration === null || mappingConfiguration === undefined || mappingConfiguration === '') {
    alert('Veuillez sélectionner une configuration de mapping.');
    return;
  }
  if (rowXpath === null || rowXpath.trim() === '') {
    alert('Veuillez saisir le XPath du noeud répétable.');
    return;
  }

  $.ajax({
    url: $('#xml-source-configuration').data('url'),
    type: 'POST',
    data: {mapping_id: mappingConfiguration, row_xpath: rowXpath},
    success: function(data) {
      var selectedOption = $('#mapping option:selected');
      selectedOption.data('source-type', data.source_type);
      selectedOption.data('source-options', data.source_options);
      selectedOption.attr('data-source-type', data.source_type);
      selectedOption.attr('data-source-options', JSON.stringify(data.source_options));
      $('#xml-source-configuration-saved').removeClass('d-none');
      loadXmlFieldMappings();
      loadXmlRowPreview();
      buildXmlWizardSummary();
      if (typeof toastr !== 'undefined') {
        toastr.success('Configuration XML enregistrée.');
      }
    },
    error: function(data) {
      if (data.responseJSON !== undefined && data.responseJSON.error_message !== undefined) {
        alert(data.responseJSON.error_message);
      } else {
        alert('Une erreur est survenue lors de l\'enregistrement de la configuration XML.');
      }
    }
  });
});

function showXmlStep(step) {
  $('.xml-step-panel').addClass('d-none');
  $('.xml-step-panel[data-step="' + step + '"]').removeClass('d-none');
  $('.xml-step-button').addClass('btn-outline-secondary').removeClass('btn-primary btn-raised');
  $('.xml-step-button[data-step="' + step + '"]').removeClass('btn-outline-secondary').addClass('btn-primary btn-raised');

  if (step === 3) {
    loadXmlRowPreview();
  }
  if (step === 5) {
    buildXmlWizardSummary();
  }
}

$(document).on('click', '.xml-step-button', function() {
  showXmlStep($(this).data('step'));
});

$(document).on('click', '.xml-next-step', function() {
  showXmlStep($(this).data('next-step'));
});

function loadXmlRowPreview(callback) {
  if ($('#xml-row-preview').length === 0) {
    return null;
  }

  var rowXpath = $('#xml_row_xpath').val();
  if (rowXpath === null || rowXpath.trim() === '') {
    $('#xml-row-preview-content').html('<div class="alert alert-warning">Veuillez enregistrer le XPath du noeud répétable avant de charger l\'aperçu.</div>');
    return null;
  }

  $('#xml-row-preview-loader').removeClass('d-none');
  return $.ajax({
    url: $('#xml-row-preview').data('url'),
    type: 'POST',
    data: {row_xpath: rowXpath},
    success: function(data) {
      renderXmlRowPreview(data);
      renderXmlPreviewFieldSuggestions(data.suggested_field_xpaths || []);
      if (typeof callback === 'function') {
        callback(data);
      }
    },
    error: function(data) {
      if (data.responseJSON !== undefined && data.responseJSON.error_message !== undefined) {
        $('#xml-row-preview-content').html('<div class="alert alert-danger">' + data.responseJSON.error_message + '</div>');
      } else {
        $('#xml-row-preview-content').html('<div class="alert alert-danger">Une erreur est survenue lors du chargement de l\'aperçu XML.</div>');
      }
    },
    complete: function() {
      $('#xml-row-preview-loader').addClass('d-none');
    }
  });
}

$(document).on('click', '#btn-load-xml-row-preview', function() {
  loadXmlRowPreview();
});

function renderXmlRowPreview(data) {
  if (data.error) {
    $('#xml-row-preview-content').html('<div class="alert alert-danger">' + data.error_message + '</div>');
    return;
  }

  var html = '<div class="alert alert-info">Noeuds trouvés : <b>' + data.row_count + '</b></div>';
  if (data.suggested_field_xpaths.length > 0) {
    html += '<h6>Chemins relatifs détectés</h6>';
    html += '<table class="table table-bordered table-sm"><thead><tr><th>XPath relatif</th><th>Exemple</th><th></th></tr></thead><tbody>';
    data.suggested_field_xpaths.forEach(function(field) {
      html += '<tr><td><code>' + escapeHtml(field.xpath) + '</code></td><td>' + escapeHtml(field.sample) + '</td>';
      html += '<td><button type="button" class="btn btn-outline-primary btn-sm xml-preview-field-choice" data-relative-xpath="' + escapeHtml(field.xpath) + '">Utiliser</button></td></tr>';
    });
    html += '</tbody></table>';
  }

  if (data.rows.length > 0) {
    html += '<h6>Aperçu des premières lignes</h6>';
    data.rows.forEach(function(row, index) {
      html += '<details class="mb-2" ' + (index === 0 ? 'open' : '') + '><summary>Ligne ' + (index + 1) + '</summary>';
      html += '<table class="table table-bordered table-sm mt-2"><tbody>';
      Object.keys(row).forEach(function(path) {
        html += '<tr><td><code>' + escapeHtml(path) + '</code></td><td>' + escapeHtml(row[path]) + '</td></tr>';
      });
      html += '</tbody></table></details>';
    });
  }

  $('#xml-row-preview-content').html(html);
}

function renderXmlPreviewFieldSuggestions(suggestions) {
  xmlDetectedPaths = suggestions || [];
  renderXmlFieldPickerList('');
}

var xmlDetectedPaths = [];
var xmlBusinessFields = [];
var xmlFieldMappings = {};
var xmlCurrentFieldIndex = 0;
var xmlCurrentPickerTarget = null;

function initXmlBusinessFields() {
  xmlBusinessFields = [];
  $('.xml-field-definition').each(function() {
    xmlBusinessFields.push({
      index: parseInt($(this).data('index'), 10),
      code: $(this).data('code'),
      label: $(this).data('label'),
      type: $(this).data('type'),
      required: $(this).data('required') == 1
    });
  });

  xmlBusinessFields.forEach(function(field) {
    if (!xmlFieldMappings[field.code]) {
      xmlFieldMappings[field.code] = getDefaultXmlFieldMapping(field);
    }
  });
  renderCurrentXmlBusinessField();
}

function getDefaultXmlFieldMapping(field) {
  return {
    mapping_code: field.code,
    mapping_type: field.type === 'date' ? 'd/m/Y' : '',
    source_config: {
      type: 'xpath',
      sources: [{xpath: ''}]
    },
    mapping_translations: [],
    mapping_transformations: []
  };
}

function loadXmlFieldMappings() {
  if ($('#xml-field-mapping').length === 0) {
    return;
  }
  initXmlBusinessFields();
  $('#xml-field-mapping-saved').addClass('d-none');

  var mappingConfiguration = $('#mapping').val();
  if (mappingConfiguration === null || mappingConfiguration === undefined || mappingConfiguration === '') {
    return;
  }

  $.ajax({
    url: $('#xml-field-mapping').data('load-url'),
    type: 'POST',
    data: {mapping_id: mappingConfiguration},
    success: function(data) {
      data.forEach(function(mapping) {
        var field = getXmlBusinessFieldByCode(mapping.mapping_code);
        if (field !== null) {
          xmlFieldMappings[mapping.mapping_code] = {
            mapping_code: mapping.mapping_code,
            mapping_type: mapping.mapping_type || (field.type === 'date' ? 'd/m/Y' : ''),
            source_config: mapping.source_config || {
              type: 'xpath',
              sources: [{xpath: mapping.relative_xpath || ''}]
            },
            mapping_translations: normalizeXmlRuleList(mapping.mapping_translations),
            mapping_transformations: normalizeXmlRuleList(mapping.mapping_transformations)
          };
        }
      });
      if (data.length > 0) {
        $('#xml-field-mapping-saved').removeClass('d-none');
      }
      renderCurrentXmlBusinessField();
      buildXmlWizardSummary();
    },
    error: function(data) {
      if (data.responseJSON !== undefined && data.responseJSON.error_message !== undefined) {
        alert(data.responseJSON.error_message);
      } else {
        alert('Une erreur est survenue lors du chargement des champs XML.');
      }
    }
  });
}

function getXmlBusinessFieldByCode(code) {
  for (var i = 0; i < xmlBusinessFields.length; i++) {
    if (xmlBusinessFields[i].code === code) {
      return xmlBusinessFields[i];
    }
  }
  return null;
}

function renderCurrentXmlBusinessField() {
  if (xmlBusinessFields.length === 0) {
    initXmlBusinessFields();
    return;
  }

  var field = xmlBusinessFields[xmlCurrentFieldIndex];
  var mapping = xmlFieldMappings[field.code] || getDefaultXmlFieldMapping(field);
  xmlFieldMappings[field.code] = mapping;

  $('#xml-current-field-label').text(field.label);
  $('#xml-current-field-code').text(field.code);
  $('#xml-current-field-required').toggleClass('d-none', !field.required);
  $('#xml-field-progress').text((xmlCurrentFieldIndex + 1) + ' / ' + xmlBusinessFields.length);

  var renderedSourceType = mapping.source_config.type === 'concat' ? 'advanced' : (mapping.source_config.type || 'xpath');
  setXmlSourceType(renderedSourceType);
  $('#xml-current-xpath').val(getXmlSourceXPath(mapping.source_config, 0));
  $('#xml-current-fixed-value').val(mapping.source_config.value || '');
  renderXmlAdvancedParts(getXmlAdvancedParts(mapping.source_config));
  renderXmlCurrentFieldOption(field, mapping);
  renderXmlCurrentTranslations(mapping.mapping_translations || []);
  renderXmlCurrentTransformations(mapping.mapping_transformations || []);

  $('#btn-previous-xml-business-field').prop('disabled', xmlCurrentFieldIndex === 0);
  $('#btn-next-xml-business-field').prop('disabled', xmlCurrentFieldIndex >= xmlBusinessFields.length - 1);
  buildXmlWizardSummary();
}

function renderXmlCurrentFieldOption(field, mapping) {
  var html = '';
  if (field.type === 'date') {
    html += '<div class="form-group"><label for="xml-current-mapping-type">Format date</label>';
    html += '<select id="xml-current-mapping-type" class="form-control xml-current-mapping-type">';
    [
      ['d/m/Y', 'JJ/MM/AAAA'],
      ['d-m-Y', 'JJ-MM-AAAA'],
      ['d/m/y', 'JJ/MM/AA'],
      ['Ymd', 'AAAAMMJJ'],
      ['Y-m-d', 'AAAA-MM-JJ'],
      ['Y/m/d', 'AAAA/MM/JJ'],
      ['excel', 'Excel']
    ].forEach(function(option) {
      html += '<option value="' + option[0] + '">' + option[1] + '</option>';
    });
    html += '</select></div>';
  } else if (field.type === 'array') {
    html += '<div class="form-group"><label for="xml-current-mapping-type">Délimiteur</label>';
    html += '<input id="xml-current-mapping-type" type="text" class="form-control xml-current-mapping-type"></div>';
  } else {
    html += '<input id="xml-current-mapping-type" type="hidden" class="xml-current-mapping-type">';
  }
  $('#xml-current-field-option').html(html);
  $('#xml-current-mapping-type').val(mapping.mapping_type || (field.type === 'date' ? 'd/m/Y' : ''));
}

function getXmlSourceXPath(sourceConfig, index) {
  if (sourceConfig.sources && sourceConfig.sources[index] && sourceConfig.sources[index].xpath) {
    return sourceConfig.sources[index].xpath;
  }
  return '';
}

function setXmlSourceType(type) {
  if (type === 'concat') {
    type = 'advanced';
  }
  $('.xml-source-type-button').addClass('btn-outline-secondary').removeClass('btn-primary btn-raised');
  $('.xml-source-type-button[data-source-type="' + type + '"]').removeClass('btn-outline-secondary').addClass('btn-primary btn-raised');
  $('.xml-source-type-panel').addClass('d-none');
  $('#xml-source-type-' + type).removeClass('d-none');
}

function getXmlAdvancedParts(sourceConfig) {
  if (!sourceConfig) {
    return [];
  }
  if (sourceConfig.type === 'advanced') {
    return sourceConfig.parts || [];
  }
  if (sourceConfig.type === 'concat') {
    var parts = [];
    var separator = sourceConfig.separator || '';
    (sourceConfig.sources || []).forEach(function(source, index) {
      if (index > 0 && separator !== '') {
        parts.push({type: 'fixed', value: separator});
      }
      parts.push({type: 'xpath', xpath: source.xpath || ''});
    });
    return parts;
  }
  return [{type: 'xpath', xpath: getXmlSourceXPath(sourceConfig, 0)}];
}

function renderXmlAdvancedParts(parts) {
  var html = '';
  if (parts.length === 0) {
    parts = [{type: 'xpath', xpath: ''}];
  }
  parts.forEach(function(part, index) {
    var type = part.type === 'fixed' ? 'fixed' : 'xpath';
    html += '<div class="xml-advanced-part-row" data-index="' + index + '">';
    html += '<div class="form-row align-items-end">';
    html += '<div class="col-md-3"><label>Type</label><select class="form-control xml-advanced-part-type xml-source-input">';
    html += '<option value="xpath"' + (type === 'xpath' ? ' selected' : '') + '>Champ XML</option>';
    html += '<option value="fixed"' + (type === 'fixed' ? ' selected' : '') + '>Valeur fixe</option>';
    html += '</select></div>';
    html += '<div class="col-md-7">';
    if (type === 'fixed') {
      html += '<label>Valeur</label><input type="text" class="form-control xml-advanced-part-value xml-source-input" value="' + escapeHtml(part.value || '') + '">';
    } else {
      html += '<label>XPath relatif</label><div class="input-group">';
      html += '<input type="text" class="form-control xml-advanced-part-xpath xml-source-input" value="' + escapeHtml(part.xpath || '') + '">';
      html += '<div class="input-group-append">';
      html += '<button type="button" class="btn btn-outline-secondary btn-open-xml-field-picker" data-target-input=".xml-advanced-part-row:eq(' + index + ') .xml-advanced-part-xpath">Sélectionner</button>';
      html += '</div></div>';
    }
    html += '</div>';
    html += '<div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-remove-xml-advanced-part">Retirer</button></div>';
    html += '</div></div>';
  });
  $('#xml-current-advanced-parts').html(html);
}

function renderXmlCurrentTranslations(translations) {
  var html = '';
  translations = normalizeXmlRuleList(translations);
  translations.forEach(function(translation) {
    html += '<div class="xml-rule-row xml-translation-row">';
    html += '<div class="form-row align-items-end">';
    html += '<div class="col-md-5"><label>Valeur fichier</label><input type="text" class="form-control xml-translation-value xml-source-input" value="' + escapeHtml(translation.value || '') + '"></div>';
    html += '<div class="col-md-5"><label>Traduction</label><input type="text" class="form-control xml-translation-target xml-source-input" value="' + escapeHtml(translation.translation || '') + '" placeholder="vide = NULL"></div>';
    html += '<div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-remove-xml-translation">Retirer</button></div>';
    html += '</div></div>';
  });
  $('#xml-current-translations').html(html);
}

function renderXmlCurrentTransformations(transformations) {
  var options = getXmlTransformationOptions();
  var html = '';
  transformations = normalizeXmlRuleList(transformations);
  transformations.forEach(function(transformation) {
    html += '<div class="xml-rule-row xml-transformation-row">';
    html += '<div class="form-row align-items-end">';
    html += '<div class="col-md-4"><label>Transformation</label><select class="form-control xml-transformation-code xml-source-input">';
    Object.keys(options).forEach(function(code) {
      html += '<option value="' + escapeHtml(code) + '"' + (transformation.transformation === code ? ' selected' : '') + '>' + escapeHtml(options[code]) + '</option>';
    });
    html += '</select></div>';
    if (transformation.transformation === 'replace') {
      var transformationOptions = transformation.transformation_options || {};
      html += '<div class="col-md-3"><label>Chercher</label><input type="text" class="form-control xml-transformation-search xml-source-input" value="' + escapeHtml(transformationOptions.search || '') + '"></div>';
      html += '<div class="col-md-3"><label>Remplacer par</label><input type="text" class="form-control xml-transformation-replace xml-source-input" value="' + escapeHtml(transformationOptions.replace || '') + '"></div>';
      html += '<div class="col-md-1 d-none"><input type="number" class="form-control xml-transformation-nb-caract xml-source-input" value="0"></div>';
    } else {
      html += '<div class="col-md-4"><label>Nombre de caractères</label><input type="number" class="form-control xml-transformation-nb-caract xml-source-input" value="' + escapeHtml(transformation.nb_caract || 0) + '"></div>';
      html += '<div class="col-md-2"></div>';
    }
    html += '<div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-remove-xml-transformation">Retirer</button></div>';
    html += '</div></div>';
  });
  $('#xml-current-transformations').html(html);
}

function getXmlTransformationOptions() {
  var options = $('#xml-field-mapping').data('transformations');
  if (typeof options === 'string') {
    try {
      options = JSON.parse(options);
    } catch (e) {
      options = {};
    }
  }
  return options || {};
}

function normalizeXmlRuleList(value) {
  if (Array.isArray(value)) {
    return value;
  }
  if (typeof value === 'string' && value !== '') {
    try {
      value = JSON.parse(value);
      return Array.isArray(value) ? value : [];
    } catch (e) {
      return [];
    }
  }
  return [];
}

function persistCurrentXmlBusinessField() {
  if (xmlBusinessFields.length === 0) {
    return;
  }

  var field = xmlBusinessFields[xmlCurrentFieldIndex];
  var sourceType = $('.xml-source-type-button.btn-primary').data('source-type') || 'xpath';
  var sourceConfig = {type: sourceType};
  if (sourceType === 'fixed') {
    sourceConfig.value = $('#xml-current-fixed-value').val();
  } else if (sourceType === 'advanced') {
    sourceConfig.parts = [];
    $('.xml-advanced-part-row').each(function() {
      var partType = $(this).find('.xml-advanced-part-type').val();
      if (partType === 'fixed') {
        sourceConfig.parts.push({type: 'fixed', value: $(this).find('.xml-advanced-part-value').val()});
      } else {
        sourceConfig.parts.push({type: 'xpath', xpath: $(this).find('.xml-advanced-part-xpath').val()});
      }
    });
  } else {
    sourceConfig.sources = [{xpath: $('#xml-current-xpath').val()}];
  }

  var translations = [];
  $('.xml-translation-row').each(function() {
    translations.push({
      value: $(this).find('.xml-translation-value').val(),
      translation: $(this).find('.xml-translation-target').val()
    });
  });

  var transformations = [];
  $('.xml-transformation-row').each(function() {
    var transformationCode = $(this).find('.xml-transformation-code').val();
    var transformation = {
      transformation: transformationCode,
      nb_caract: $(this).find('.xml-transformation-nb-caract').val() || 0
    };
    if (transformationCode === 'replace') {
      transformation.transformation_options = {
        search: $(this).find('.xml-transformation-search').val(),
        replace: $(this).find('.xml-transformation-replace').val()
      };
    }
    transformations.push(transformation);
  });

  xmlFieldMappings[field.code] = {
    mapping_code: field.code,
    mapping_type: $('#xml-current-mapping-type').val(),
    source_config: sourceConfig,
    mapping_translations: translations,
    mapping_transformations: transformations
  };
}

$(document).on('click', '.xml-source-type-button', function() {
  persistCurrentXmlBusinessField();
  setXmlSourceType($(this).data('source-type'));
  persistCurrentXmlBusinessField();
  $('#xml-field-mapping-saved').addClass('d-none');
  buildXmlWizardSummary();
});

$(document).on('input change', '.xml-source-input, .xml-current-mapping-type', function() {
  persistCurrentXmlBusinessField();
  $('#xml-field-mapping-saved').addClass('d-none');
  buildXmlWizardSummary();
});

$(document).on('click', '#btn-previous-xml-business-field', function() {
  persistCurrentXmlBusinessField();
  if (xmlCurrentFieldIndex > 0) {
    xmlCurrentFieldIndex--;
    renderCurrentXmlBusinessField();
  }
});

$(document).on('click', '#btn-next-xml-business-field', function() {
  persistCurrentXmlBusinessField();
  if (xmlCurrentFieldIndex < xmlBusinessFields.length - 1) {
    xmlCurrentFieldIndex++;
    renderCurrentXmlBusinessField();
  }
});

$(document).on('click', '#btn-add-xml-advanced-xpath-part', function() {
  persistCurrentXmlBusinessField();
  var field = xmlBusinessFields[xmlCurrentFieldIndex];
  if (!xmlFieldMappings[field.code].source_config.parts) {
    xmlFieldMappings[field.code].source_config.parts = [];
  }
  xmlFieldMappings[field.code].source_config.parts.push({type: 'xpath', xpath: ''});
  renderCurrentXmlBusinessField();
});

$(document).on('click', '#btn-add-xml-advanced-fixed-part', function() {
  persistCurrentXmlBusinessField();
  var field = xmlBusinessFields[xmlCurrentFieldIndex];
  if (!xmlFieldMappings[field.code].source_config.parts) {
    xmlFieldMappings[field.code].source_config.parts = [];
  }
  xmlFieldMappings[field.code].source_config.parts.push({type: 'fixed', value: ''});
  renderCurrentXmlBusinessField();
});

$(document).on('click', '.btn-remove-xml-advanced-part', function() {
  $(this).closest('.xml-advanced-part-row').remove();
  persistCurrentXmlBusinessField();
  renderCurrentXmlBusinessField();
});

$(document).on('change', '.xml-advanced-part-type', function() {
  persistCurrentXmlBusinessField();
  renderCurrentXmlBusinessField();
});

$(document).on('click', '#btn-add-xml-translation', function() {
  persistCurrentXmlBusinessField();
  var field = xmlBusinessFields[xmlCurrentFieldIndex];
  xmlFieldMappings[field.code].mapping_translations.push({value: '', translation: ''});
  renderCurrentXmlBusinessField();
});

$(document).on('click', '.btn-remove-xml-translation', function() {
  $(this).closest('.xml-translation-row').remove();
  persistCurrentXmlBusinessField();
  renderCurrentXmlBusinessField();
});

$(document).on('click', '#btn-add-xml-transformation', function() {
  persistCurrentXmlBusinessField();
  var field = xmlBusinessFields[xmlCurrentFieldIndex];
  var options = getXmlTransformationOptions();
  var firstTransformation = Object.keys(options)[0] || '';
  xmlFieldMappings[field.code].mapping_transformations.push({transformation: firstTransformation, nb_caract: 0, transformation_options: firstTransformation === 'replace' ? {search: '', replace: ''} : null});
  renderCurrentXmlBusinessField();
});

$(document).on('change', '.xml-transformation-code', function() {
  persistCurrentXmlBusinessField();
  renderCurrentXmlBusinessField();
});

$(document).on('click', '.btn-remove-xml-transformation', function() {
  $(this).closest('.xml-transformation-row').remove();
  persistCurrentXmlBusinessField();
  renderCurrentXmlBusinessField();
});

$(document).on('click', '.btn-open-xml-field-picker', function() {
  xmlCurrentPickerTarget = $(this).data('target-input');
  $('#xml-field-picker-filter').val('');
  $('#xmlFieldPickerModal').modal();
  if (xmlDetectedPaths.length === 0) {
    $('#xml-field-picker-list').html('<div class="alert alert-info"><i class="fa fa-spin fa-spinner"></i> Chargement des champs XML détectés...</div>');
    loadXmlRowPreview(function() {
      renderXmlFieldPickerList('');
    });
    return;
  }
  renderXmlFieldPickerList('');
});

$(document).on('input', '#xml-field-picker-filter', function() {
  renderXmlFieldPickerList($(this).val());
});

$(document).on('click', '.xml-field-picker-choice', function() {
  if (xmlCurrentPickerTarget !== null) {
    $(xmlCurrentPickerTarget).val($(this).data('relative-xpath')).trigger('input');
  }
  $('#xmlFieldPickerModal').modal('hide');
});

function renderXmlFieldPickerList(filter) {
  if ($('#xml-field-picker-list').length === 0) {
    return;
  }
  filter = (filter || '').toLowerCase();
  if (xmlDetectedPaths.length === 0) {
    $('#xml-field-picker-list').html('<div class="alert alert-warning">Aucun champ XML détecté. Charge l\'aperçu après avoir enregistré le noeud répétable.</div>');
    return;
  }

  var html = '<table class="table table-bordered table-sm"><thead><tr><th>XPath relatif</th><th>Exemple</th><th></th></tr></thead><tbody>';
  var resultCount = 0;
  xmlDetectedPaths.forEach(function(field) {
    var xpath = field.xpath || '';
    var sample = field.sample || '';
    if (filter === '' || xpath.toLowerCase().indexOf(filter) !== -1 || sample.toLowerCase().indexOf(filter) !== -1) {
      resultCount++;
      html += '<tr class="xml-field-picker-choice" data-relative-xpath="' + escapeHtml(xpath) + '" style="cursor:pointer">';
      html += '<td><code>' + escapeHtml(xpath) + '</code></td><td>' + escapeHtml(sample) + '</td>';
      html += '<td><button type="button" class="btn btn-primary btn-sm">Choisir</button></td></tr>';
    }
  });
  if (resultCount === 0) {
    html += '<tr><td colspan="3" class="text-muted">Aucun champ ne correspond au filtre.</td></tr>';
  }
  html += '</tbody></table>';
  $('#xml-field-picker-list').html(html);
}

$(document).on('click', '.xml-preview-field-choice', function() {
  showXmlStep(4);
  setXmlSourceType('xpath');
  $('#xml-current-xpath').val($(this).data('relative-xpath')).trigger('input');
});

$(document).on('click', '#btn-save-xml-field-mappings', function() {
  persistCurrentXmlBusinessField();
  var mappingConfiguration = $('#mapping').val();
  if (mappingConfiguration === null || mappingConfiguration === undefined || mappingConfiguration === '') {
    alert('Veuillez sélectionner une configuration de mapping.');
    return;
  }

  var sourceOptions = getSelectedMappingSourceOptions();
  if (!sourceOptions.row_xpath) {
    alert('Veuillez enregistrer le XPath du noeud répétable avant de mapper les champs XML.');
    return;
  }

  var mappings = [];
  xmlBusinessFields.forEach(function(field) {
    var mapping = xmlFieldMappings[field.code];
    if (isXmlFieldMappingFilled(mapping)) {
      mappings.push({
        mapping_code: field.code,
        mapping_type: mapping.mapping_type,
        source_config: mapping.source_config,
        mapping_translations: mapping.mapping_translations || [],
        mapping_transformations: mapping.mapping_transformations || []
      });
    }
  });

  $.ajax({
    url: $('#xml-field-mapping').data('save-url'),
    type: 'POST',
    data: {mapping_id: mappingConfiguration, mappings: mappings, xml_mapping_save: true},
    success: function() {
      $('#xml-field-mapping-saved').removeClass('d-none');
      buildXmlWizardSummary();
      if (typeof toastr !== 'undefined') {
        toastr.success('Champs XML enregistrés.');
      }
    },
    error: function(data) {
      if (data.responseJSON !== undefined && data.responseJSON.error_message !== undefined) {
        alert(data.responseJSON.error_message);
      } else {
        alert('Une erreur est survenue lors de l\'enregistrement des champs XML.');
      }
    }
  });
});

function isXmlFieldMappingFilled(mapping) {
  if (!mapping || !mapping.source_config) {
    return false;
  }
  if (mapping.source_config.type === 'fixed') {
    return mapping.source_config.value !== undefined && String(mapping.source_config.value).trim() !== '';
  }
  if (mapping.source_config.type === 'advanced') {
    return (mapping.source_config.parts || []).some(function(part) {
      if (part.type === 'fixed') {
        return part.value !== undefined && String(part.value) !== '';
      }
      return part.xpath !== undefined && String(part.xpath).trim() !== '';
    });
  }
  if (!mapping.source_config.sources || mapping.source_config.sources.length === 0) {
    return false;
  }
  return mapping.source_config.sources.some(function(source) {
    return source.xpath !== undefined && String(source.xpath).trim() !== '';
  });
}

function buildXmlWizardSummary() {
  if ($('#xml-wizard-summary').length === 0) {
    return;
  }

  var rowXpath = $('#xml_row_xpath').val() || '';
  var mappedFields = [];
  persistCurrentXmlBusinessField();
  xmlBusinessFields.forEach(function(field) {
    var mapping = xmlFieldMappings[field.code];
    if (isXmlFieldMappingFilled(mapping)) {
      mappedFields.push({
        code: field.code,
        label: field.label,
        source: formatXmlSourceConfig(mapping.source_config),
        translations: (mapping.mapping_translations || []).length,
        transformations: (mapping.mapping_transformations || []).length
      });
    }
  });

  var html = '<table class="table table-bordered table-sm">';
  html += '<tbody>';
  html += '<tr><th>Noeud répétable</th><td><code>' + escapeHtml(rowXpath) + '</code></td></tr>';
  html += '<tr><th>Champs mappés</th><td>' + mappedFields.length + '</td></tr>';
  html += '</tbody></table>';

  if (mappedFields.length > 0) {
    html += '<table class="table table-bordered table-sm"><thead><tr><th>Champ</th><th>Source</th><th>Traductions</th><th>Transformations</th></tr></thead><tbody>';
    mappedFields.forEach(function(field) {
      html += '<tr><td>' + escapeHtml(field.label) + '<br><small><code>' + escapeHtml(field.code) + '</code></small></td><td>' + field.source + '</td><td>' + field.translations + '</td><td>' + field.transformations + '</td></tr>';
    });
    html += '</tbody></table>';
  } else {
    html += '<div class="alert alert-warning">Aucun champ XML n\'est encore mappé.</div>';
  }

  $('#xml-wizard-summary').html(html);
}

function formatXmlSourceConfig(sourceConfig) {
  if (!sourceConfig) {
    return '';
  }
  if (sourceConfig.type === 'fixed') {
    return 'Valeur fixe : <code>' + escapeHtml(sourceConfig.value || '') + '</code>';
  }
  if (sourceConfig.type === 'concat') {
    var separator = sourceConfig.separator || '';
    var parts = (sourceConfig.sources || []).map(function(source) {
      return '<code>' + escapeHtml(source.xpath || '') + '</code>';
    });
    return 'Concaténation : ' + parts.join(' + ') + (separator !== '' ? ' / séparateur <code>' + escapeHtml(separator) + '</code>' : '');
  }
  if (sourceConfig.type === 'advanced') {
    var advancedParts = (sourceConfig.parts || []).map(function(part) {
      if (part.type === 'fixed') {
        return '<code>"' + escapeHtml(part.value || '') + '"</code>';
      }
      return '<code>' + escapeHtml(part.xpath || '') + '</code>';
    });
    return 'Champ avancé : ' + advancedParts.join(' + ');
  }
  return 'XPath : <code>' + escapeHtml(getXmlSourceXPath(sourceConfig, 0)) + '</code>';
}

function escapeHtml(value) {
  return String(value === null || value === undefined ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function updateRecapitulatif() {
  $('#tableRecapitulatif').html('<tr><th colspan="5">Chargement en cours ...</th></tr>');
  var htmlRecapitulatif = "";
  $('#tr_mapping th').each(function() {
    if (!! $(this).data('lib-colonne')) {
      var libColonne = $(this).data('lib-colonne');
    } else {
      var libColonne = "";
    }
    if (!! $(this).data('entete-fichier')) {
      var enteteFichier = $(this).data('entete-fichier');
    } else {
      var enteteFichier = "";
    }
    if (!! $(this).data('code-mapping')) {
      var codeMapping = $(this).data('code-mapping');
    } else {
      var codeMapping = "";
    }
    if (!! $(this).data('type-mapping')) {
      var typeMapping = $(this).data('type-mapping');
    } else {
      var typeMapping = "";
    }
    if (!! $(this).data('translations')) {
      var translationsTemp = JSON.parse($(this).data('translations'));
      var translations = translationsTemp.length + " traductions";
    } else {
      var translations = "";
    }
    if (!! $(this).data('transformations')) {
      var transformationsTemp = JSON.parse($(this).data('transformations'));
      var transformations = transformationsTemp.length + " transformations";
    } else {
      var transformations = "";
    }
    if (!! $(this).data('id-mapping')) {
      var idMappingValue = $(this).data('id-mapping');
    } else {
      var idMappingValue = "";
    }

    htmlRecapitulatif += "<tr>";
    htmlRecapitulatif += "<td>" + libColonne + "</td>";
    htmlRecapitulatif += "<td>" + enteteFichier + "</td>";
    htmlRecapitulatif += "<td>" + codeMapping + "</td>";
    htmlRecapitulatif += "<td>" + typeMapping + "</td>";
    if (translations !== ''){
      htmlRecapitulatif += "<td><button class='btn btn-raised btn-primary btn-sm btn-edit-translations' data-mapping-id='"+idMappingValue+"'><small>" + translations + "</small></button></td>";
    } else {
      htmlRecapitulatif += '<td class="text-danger">Mapping en attente</td>';
    }
    if (transformations !== ''){
      htmlRecapitulatif += "<td><button class='btn btn-raised btn-primary btn-sm btn-edit-transformations' data-mapping-id='"+idMappingValue+"'><small>" + transformations + "</small></button></td>";
    } else {
      htmlRecapitulatif += '<td class="text-danger">Mapping en attente</td>';
    }

    htmlRecapitulatif += "</tr>";
  });

  var mappingConfiguration = $('#mapping').val();
  var data = {mapping_id: mappingConfiguration};
  $.ajax({
    url: $('#tableRecapitulatif').data('url'),
    type: 'POST',
    data: data,
    success: function (data) {
      $('#tableRecapitulatif').html(data);
    },
    error: function () {
      showSweetAlert('top-end', 'error', 'Une erreur est survenue lors de la sélection du champs. Veuillez réessayer plus tard.', true, 3000);
    }
  });
  $('#tableRecapitulatif').html(htmlRecapitulatif);
  updateObligatoiresAMapper();
  loadModeDecoupageChamps();
}

$(document).on('click', '.btn-edit-translations', function(){
  let loader = $("#loader-transitions-translations");
  loader.removeClass('d-none');
  $("#translations-card").addClass('d-none');
  $("#transformations-card").addClass('d-none');
  let mappingId = $(this).data('mapping-id');
  let data = {mapping_id: mappingId};
  $.ajax({
    url: $('#translations-card').data('url'),
    type: 'POST',
    data: data,
    success: function (data){
      $("#translations-card").html(data).removeClass('d-none');
      document.getElementById('translations-card').scrollIntoView();
    },
    error: function (){
      alert('Une erreur est survenue lors du chargement de la page de gestion des traductions');
    },
    complete: function(){
      loader.addClass('d-none');
    }
  });
});

$(document).on('click', '.btn-edit-transformations', function(){
  let loader = $("#loader-transitions-translations");
  loader.removeClass('d-none');
  $("#translations-card").addClass('d-none');
  $("#transformations-card").addClass('d-none');
  let mappingId = $(this).data('mapping-id');
  let data = {mapping_id: mappingId};
  $.ajax({
    url: $('#transformations-card').data('url'),
    type: 'POST',
    data: data,
    success: function (data){
      $("#transformations-card").html(data).removeClass('d-none');
      document.getElementById('transformations-card').scrollIntoView();
    },
    error: function (){
      alert('Une erreur est survenue lors du chargement de la page de gestion des transformations');
    },
    complete: function(){
      loader.addClass('d-none');
    }
  });
});

function updateObligatoiresAMapper() {
  var divObligatoire = $('#divObligatoiresAMapper');
  $('#btnValidMappingAndRunIntegration').addClass('disabled');
  divObligatoire.html("");

  let mappingConfiguration = $('#mapping').val();
  if (mappingConfiguration !== null){
    let data = {mapping_id: mappingConfiguration};
    $.ajax({
      url: divObligatoire.data('url'),
      type: 'POST',
      data: data,
      success: function (data) {
        var text = "<h5>Liste des champs obligatoires non mappé pour l'instant : <span class='text-danger'>";
        var first = true;
        for (var v in data) {
          if (first) {
            first = false;
            text += data[v].libelle;
          } else {
            text += ", " + data[v].libelle;
          }
        }

        if (first) {
          $('#btnValidMappingAndRunIntegration').removeClass('disabled');
        }
        text += "</span></h5>";
        $('#divObligatoiresAMapper').html(text);
      },
      error: function () {
        showSweetAlert('top-end', 'error', 'Une erreur est survenue lors de la récupération de la configuration. Veuillez réessayer plus tard.', true, 3000);
      },
      complete: function() {
        $('#mappingConfigurationLoad').addClass('d-none');
        $('#affichageMappingGlobal').removeClass('d-none');
      }
    });
  }
}

$(document).on('change', '#champs', function() {
  var data = {lib_colonne: $('#champs').data('lib-colonne'), champ: $(this).val()};
  $.ajax({
    url: $('#champs').data('url'),
    type: 'POST',
    data: data,
    success: function (data) {
      $('#options-div').html(data);
      $('#btn-add-anomalie').prop('disabled', false);
    },
    error: function () {
      alert('Une erreur est survenue lors de la sélection du champs. Veuillez réessayer plus tard.');
    }
  });
});

$(document).on('click', '#btn-remove-correspondance', function(){
  let th = $('.mapping_data_value[data-lib-colonne="'+$('#champs').data('lib-colonne')+'"]');
  th.data('code-mapping', '');
  th.html('');
  th.removeClass('mapping_data_value_ok');
  $('#modalSelectChamps').modal('hide');
  saveMapping();
});

$(document).on('click', '#btn-valid-champ', function() {
  var th = $('.mapping_data_value[data-lib-colonne="'+$(this).data('lib-colonne')+'"]');
  var selectChamps = $('#champs');
  th.data('code-mapping', selectChamps.val());
  th.html(selectChamps.val());

  th.addClass('mapping_data_value_ok');
  $('#modalSelectChamps').modal('hide');

  saveMapping();
});

$(document).on('click', '#btn-save-mapping-champ-date', function() {
  var th = $('.mapping_data_value[data-lib-colonne="'+$(this).data('lib-colonne')+'"]');
  var selectChamps = $('#champs');

  th.data('code-mapping', selectChamps.val());
  th.data('type-mapping', $('#format_date').val());
  th.html(selectChamps.val());

  th.addClass('mapping_data_value_ok');
  $('#modalSelectChamps').modal('hide');

  saveMapping();
});

$(document).on('click', '#btn-save-mapping-champ-array', function() {
  var th = $('.mapping_data_value[data-lib-colonne="'+$(this).data('lib-colonne')+'"]');
  var selectChamps = $('#champs');

  th.data('code-mapping', selectChamps.val());
  th.data('type-mapping', $('#delimiteur').val());
  th.html(selectChamps.val());

  th.addClass('mapping_data_value_ok');
  $('#modalSelectChamps').modal('hide');

  saveMapping();
});

$(document).on('change', '#mapping', function() {
  console.log('load mapping change ')
  loadConfiguration();
});


$(document).on('click', '#btn_add_configuration', function() {
  let libelle = $('#mapping_configuration_libelle').val();
  if (libelle !== "") {

    var formData = new FormData($('#form-add-configuration')[0]);

    $.ajax({
      url: $(this).data('url'),
      type: 'POST',
      enctype: 'multipart/form-data',
      data: formData,
      processData: false,  // tell jQuery not to process the data
      contentType: false,  // tell jQuery not to set contentType
      success: function () {
        $('#addConfigurationModal').modal('hide');
        $('#addConfigurationModal').trigger('click');
        loadMappingConfigurations();
      },
      error: function () {
        showSweetAlert('top-end', 'error', 'Une erreur est survenue lors de la sélection du champs. Veuillez réessayer plus tard.', true, 3000);
      }
    });
  } else {
    showSweetAlert('top-end', 'error',"Il faut saisir un libellé pour la configuration");
  }
});

$(document).on('click', '#btn_remove_mapping_configuration', function() {
  var mappingConfiguration = $('#mapping').val();
  if (mappingConfiguration !== null ) {
    $.ajax({
      url: $(this).data('url'),
      type: 'POST',
      data: {mapping_id: mappingConfiguration},
      success: function () {
        loadMappingConfigurations();
      },
      error: function () {
        toastr.error('Une erreur est survenue lors de la suppression de la configuration. Veuillez réessayer plus tard.');
      }
    });
  } else {
    toastr.error('Veuillez sélectionner un mapping avant de tenter de le supprimer.');
  }
});

$(document).on('click', '#btn_export_mapping_configuration', function() {
  var mappingConfiguration = $('#mapping').val();
  if (mappingConfiguration !== null ) {
    $.ajax({
      url: $(this).data('url'),
      type: 'POST',
      data: {mapping_id: mappingConfiguration},
      success: function (data) {
        if (data.success) {
          document.location = data.url;
        } else {
          toastr.error('Une erreur est survenue lors de la récupération du fichier. Veuillez réessayer plus tard.');
        }
      },
      error: function () {
        toastr.error('Une erreur est survenue lors de l\'export de la configuration. Veuillez réessayer plus tard.');
      }
    });
  } else {
    toastr.error('Veuillez sélectionner un mapping avant de l\'exporter.');
  }
});
