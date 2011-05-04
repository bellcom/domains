$(document).ready(function() {

  $.facebox.settings.closeImage = '/design/desktop/images/closelabel.png';
  $.facebox.settings.loadingImage = '/design/desktop/images/loading.gif';

  $("#accountsToDomains #accountName").autocomplete({
    source: '/service/ajax/getAccount/',
    minLength: 2,
    select: function( event, ui ) {
      $("#accountID").val(ui.item.id);
      $("#accountName").val(ui.item.value);
      return false;
    }
  });

  $("#search").submit(function(e){
    e.preventDefault();
    $("#searchQuery").autocomplete( "close" );
    $("#searchQuery").autocomplete( "disable" );
    var type = $("#search_form input:radio:checked").val();
    var query = $("#searchQuery").val();
    var wildcards = ( $("#wildcards").attr('checked') ? 'both' : 'single' )  ;
    $.ajax({
      url: '/service/ajax/search/'+ type +'/'+ query +'/'+ wildcards,
      dataType: 'json',
      success: function( data ) {
        $("#result tbody").html('');
        for (var i in data)
        {
          $("#result tbody").append(data[i].desc);
        }
      }
      });
    $("#searchQuery").autocomplete( "enable" );
  });

  $("#searchQuery").autocomplete({
    source: function( request, response ) {
              $.ajax({
                url: '/service/ajax/search/'+ $("#search_form input:radio:checked").val() +'/'+ request.term,
                dataType: 'json',
                success: function( data ) {
                  response( data );
                }
              });
            },
    minLength: 2,
    select: function( event, ui ) {
      $("#result tbody").html(ui.item.desc);
      return false;
    }
  });

  $("#accountsToDomains").submit(function(e) {
    e.preventDefault();
    $.ajax({
      url: '/service/ajax/accountsToDomains/',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(data){
        if (data.error)
        {
          setMessage(data.msg,data.msg_type);
        }
        else
        {
          setMessage(data.msg,data.msg_type);
          $("#domains").html(data.content);
          $("#accountName").val('');
          $("#accountID").val('');
        }
      }
    });
  });

  $("body").delegate(".ajaxRequest",'click',function(e) {
    e.preventDefault();
    ajaxRequest( this.href, {}, 'facebox');
  });

  $("body").delegate("#facebox form","submit", function(e){
    e.preventDefault();
    var formData = $(this).serialize();
    var ajaxUrl  = $(this).attr('action');
    $.ajax({
      url: ajaxUrl,
      data: formData,
      dataType: 'json',
      success: function(data){
        if (data.error)
        {
          setMessage(data.msg,data.msg_type);
        }
        else
        {
          setMessage(data.msg,data.msg_type);
          $.facebox.close();
        }
      }
    });
  });

  var domains = {
    config : {
      domainsHtml : ''
    }
  };

  $("#domainFilter").keyup(function() {
             if ( domains.config.domainsHtml.length === 0 )
             {
               domains.config.domainsHtml = $('#domains option');
             }
             var input = $("#domainFilter").val();
             var name = '';

             // smart case detection :)
             var hasUpperCase = false;
             if ( input != input.toLowerCase() )
             {
               hasUpperCase = true;
               $("#smartcase").show();
             }
             else
             {
               $("#smartcase").hide();
             }

             // TODO: should not be called on every keypress
             $("#domains").html(domains.config.domainsHtml);
             $("#domains option").each(function() {
                if ( !hasUpperCase )
                {
                  name = $(this).text().toLowerCase();
                }
                else
                {
                  name = $(this).text();
                }
                if ( name.indexOf( input ) == -1 )
                {
                  // have to use remove() instead of hide(), because arrow down keypress in the case list will still select the hidden elements
                  $(this).remove();
                }
             });
           });

  var faceboxCloseHandler = function() {
      // TODO: only if sorting was changed 
      ajaxRequest('/service/ajax/getServerList/','',$("#servers"), false);
      $(".tablesorter").tablesorter({
        widgets: ['zebra']
      });
      $(document).unbind('close.facebox',this);
      $(document).unbind('reveal.facebox', faceboxRevealHandler );
    };

  var faceboxRevealHandler = function() {
    $( "#enabledFields, #avaliableFields" ).sortable({
      connectWith: ".connectedSortable",
      placeholder: "ui-state-highlight",
      update: function(event, ui) {
        if ( event.target.id === 'enabledFields' )
        {
          var fields = $(this).sortable('serialize', { expression: /(.+)=(.+)/ });
          $.ajax({
            url: '/service/ajax/setEnabledFields/?type=servers',
            data: fields,
            dataType: 'json',
            success: function(data){
              if (data.error)
              {
                setMessage(data.msg,data.msg_type);
              }
            }
          });
        }
      }
      /*stop: function() {
      }*/
    }).disableSelection();
  };


  $("#showFieldSelector").click(function(e){
    e.preventDefault();
    $(document).bind('close.facebox', faceboxCloseHandler );
    $(document).bind('reveal.facebox', faceboxRevealHandler );
    $.facebox({ ajax: '/service/ajax/getFieldList/' });
  });

  $(".tablesorter").tablesorter({
    widgets: ['zebra']
  });

  $(".tooltip_trigger").tooltip().dynamic({ bottom: { direction: 'down', bounce: true } });
});

function setMessage(msg,type)
{
  //$("#messages").toggle();  
  $("#messages").addClass(type);
  $("#messages").slideDown();
  $("#messages").html(msg);
  $("#messages").fadeOut(6000);    
}

function ajaxRequest( url, params, dest, async )
{
  var asyncParam = typeof(async) != 'undefined' ? async : true;
  var showInFacebox = ( dest === 'facebox' ? true : false );
  $.ajax({
    url: url,
    async: asyncParam,
    data: params,
    dataType: 'json',
    success: function(data) {
      if (data.error)
      {
        setMessage(data.msg,data.msg_type);
      }
      else
      {
        if ( showInFacebox )
        {
          $.facebox(data.content);
        }
        else
        {
          dest.html(data.content);
        }
      }
    }
  });
}
