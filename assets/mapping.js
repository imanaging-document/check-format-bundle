require('bootstrap');

$( document ).ready( function () {
  loadMappingConfigurations();
});

let requestPending = false;


$(".delete-file").click(function(){
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

$('#btnModeRecapitulaif').click(function() {
  $('#divRecapitulatif').removeClass('d-none');
  $('#divModeAvance').addClass('d-none');
  $('#divMappingMode').addClass('d-none');
  $('#btnModeRecapitulaif').addClass('btn-success btn-raised').removeClass('btn-outline-secondary');
  $('#btnModeMapping').addClass('btn-outline-secondary').removeClass('btn-warning btn-raised');
  $('#btnModeAvance').addClass('btn-outline-secondary').removeClass('btn-info btn-raised');
});

$('#btnModeMapping').click(function() {
  $('#divRecapitulatif').addClass('d-none');
  $('#divModeAvance').addClass('d-none');
  $('#divMappingMode').removeClass('d-none');
  $('#btnModeRecapitulaif').addClass('btn-outline-secondary').removeClass('btn-raised btn-success');
  $('#btnModeMapping').removeClass('btn-outline-secondary').addClass('btn-warning btn-raised');
  $('#btnModeAvance').addClass('btn-outline-secondary').removeClass('btn-info btn-raised');
});

$('#btnModeAvance').click(function() {
  $('#divRecapitulatif').addClass('d-none');
  $('#divMappingMode').addClass('d-none');
  $('#divModeAvance').removeClass('d-none');
  $('#btnModeRecapitulaif').removeClass('btn-success btn-raised').addClass('btn-outline-secondary');
  $('#btnModeMapping').addClass('btn-outline-secondary').removeClass('btn-warning btn-raised');
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

function loadConfiguration() {
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