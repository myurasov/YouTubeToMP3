tomp3org = {};

tomp3org.fxDuration = 333;

/**
 * Debug string
 */
tomp3org.getDebugString = function()
{
  return 'nodebug'; // 'XDEBUG_SESSION_START=netbeans-xdebug';
}

/**
 * Switch between quality levels
 */
tomp3org.switchQuality = function(quality)
{
  document.getElementById('selectQuality').className = quality;
  tomp3org.quality = quality;
}

/**
 * Initialization
 */
tomp3org.init = function()
{
  // Init UI
  tomp3org.initUi();

  // Start conversion if link descriptor is present
  if (window.location.hash.length > 1) {
    var fxDuration = tomp3org.fxDuration;
    tomp3org.fxDuration = 0;
    tomp3org.showPage('page2');
    tomp3org.fxDuration = fxDuration;
    tomp3org.doConversion();
  }

//  tomp3org.showPage('page2'); // xxx
//  $('#conversionResult .result').show();
}

/**
 * Init UI
 */
tomp3org.initUi = function()
{
  // Set current page
  tomp3org.pageId = 'page1';

  // Add .pressable behaviour
  $(".pressable").mousedown(function() {
    if (!$(this).hasClass('disabled'))
      $(this).addClass('pressed');
  }).mouseup(function(){
    $(this).removeClass('pressed');
  }).mouseout(function(){
    $(this).removeClass('pressed')
  });

  // Set content page height
  $('#contentPages').height($('#page1').height());

  // Quality
  tomp3org.switchQuality('std');

  // Keyboard shortcuts
  $('body').keypress(function(event){
    if (tomp3org.pageId == 'page1')
    {
      if (event.charCode == 13 /* enter */)
        $('#buttonGo').click();
      if (event.charCode == 108 /* l */)
        $('#selectQualityA').mousedown();
      if (event.charCode == 115 /* s */)
        $('#selectQualityB').mousedown();
      if (event.charCode == 104 /* h */)
        $('#selectQualityC').mousedown();
      //console.log(event.charCode);
    }
    else if (tomp3org.pageId == 'page2')
    {
      if (event.charCode == 98 /* b */)
        $('#buttonBack').click();
    }
  });

  // Set focus to input field
  $('#inputUrl').focus().keypress(function(event){
    if (event.charCode != 13)
      event.stopPropagation();
  });

  // Result option pages
  if ($.browser.opera)
    $('.resultOptions .title').css({borderBottomWidth: '4px'});
}

tomp3org.toggleResultOptions = function(option, forceHide)
{
  forceHide = forceHide || false;

  // show options page
  var showF = function(callback) {
    // Move panel into place

    $('#' + option + 'ResultOptions .title').css(
      {left: $('#' + option + 'Link').position().left
      - parseInt($('.resultOptions .title').css('paddingLeft'))
      - parseInt($('.resultOptions .title').css('borderLeftWidth'))});

    $('#' + option + 'ResultOptions .content').width(
      $('#conversionResult').width());

    $('#' + option + 'ResultOptions .content').css(
      {left: $('#conversionResult td')
        .position().left});

    $('#' + option + 'ResultOptions').fadeIn(tomp3org.fxDuration, callback);
  }

  var adjustHeightF = function(option) {
    // Adjust content page height

    var adjF = function(){
      $('#contentPages').height($('#' + tomp3org.pageId).height());
    }

    if (option == undefined)
      $('#resultsOptionsPlaceholder')
        .animate(
          {height: 0},
          {step: adjF, duration: tomp3org.fxDuration, complete: function(){
              $('#resultsOptionsPlaceholder').addClass('hidden');
              adjF();
          }});
    else
      $('#resultsOptionsPlaceholder')
        .removeClass('hidden')
        .animate(
          {height: $('#' + option + 'ResultOptions .content').outerHeight()},
          {step: adjF, duration: tomp3org.fxDuration, complete: adjF})
  }
  
  var hideF = function(callback) {
    $('#' + tomp3org._resultOptionsShown + 'ResultOptions')
      .fadeOut(tomp3org.fxDuration, function() {callback();$(this).hide()});
  }

  if (tomp3org._resultOptionsShown == undefined) {
    if (!forceHide) showF(function(){tomp3org._resultOptionsShown = option});
    adjustHeightF(option);
  }
  else if (tomp3org._resultOptionsShown == option) {
    hideF(function(){tomp3org._resultOptionsShown = undefined;});
    adjustHeightF();
  }
  else {
    hideF(function(){});
    if (!forceHide) showF(function(){tomp3org._resultOptionsShown = option});
    adjustHeightF(option);
  }
}

/**
 * Start progress animation
 */
tomp3org.startProgressAnimation = function()
{
  // Thumbnail

  var f = function(elem, sign) {
    var prop = $.browser.msie
      ? {'background-position-x' : (sign + '=300px')}
      : {backgroundPosition : (sign + '=300px -=0px')};
    $(elem).animate(prop, 5000, "linear",
      function(){f(elem, sign)});
  }

  f($('#conversionResult .thumbnail .top'), '+');
  f($('#conversionResult .thumbnail .bottom'), '-');
}

/**
 * Stop progress animation
 */
tomp3org.stopProgressAnimation = function()
{
  // Thumbnail
  
  var t, b, dt, db, duration = tomp3org.fxDuration, css;
  var bgWidth = 120;
  
  t = $('#conversionResult .thumbnail .top');
  b = $('#conversionResult .thumbnail .bottom');

  t.stop();
  b.stop();

  css = $.browser.msie ? 'backgroundPositionX' : 'backgroundPosition';

  dt = parseInt(t.css(css).split(' ')[0]);
  dt = dt + bgWidth - dt % bgWidth;
  
  db = parseInt(b.css(css).split(' ')[0]);
  db = db - bgWidth - db % bgWidth;
  
  t.animate(
    $.browser.msie
      ? {'background-position-x' : dt + 'px'}
      : {backgroundPosition : dt + 'px -=0px'},
    duration
  );

  b.animate(
    $.browser.msie
      ? {'background-position-x' : db + 'px'}
      : {backgroundPosition : db + 'px -=0px'},
    duration
  );
}

/**
 * Insert url to text field
 */
tomp3org.insertUrl = function(obj)
{
  $('#inputUrl').val((obj.innerHTML));
}

/**
 * Start conversion
 */
tomp3org.doConversion = function()
{
  var data;

  if (window.location.hash.length > 1) {
    data = {
      linkDescriptor: window.location.hash.substr(1),
      url: '',
      quality: '',
      format: ''
    }
  } else {
    data = {
      url : $('#inputUrl').val(),
      quality : tomp3org.quality,
      format : 'mp3',
      linkDescriptor: ''
    };

    // Check input
    if ($('#inputUrl').val() == '')
    {
      alert("Please enter video URL!");
      $('#inputUrl').focus();
      return;
    }

    // Change UI
    $('#conversionResult .title').text(data.url)
      .attr('title', data.url);
  }

  // Change UI
  tomp3org.showPage('page2');
  tomp3org.startProgressAnimation();
  $('.conversionResult .operation').text('Queueing');

  // Start conversion

  tomp3org.callApi('Conversion.add', data).done(function(result){

    var onError = function(message) {
      $('#conversionResult .progress').fadeOut(tomp3org.fxDuration, function() {
        $('#conversionResult .error').text(message).fadeIn(tomp3org.fxDuration);
      });
      tomp3org.stopProgressAnimation();
      $('#buttonBack').removeClass('disabled');
    };

    // Error adding task
    if (result.error != 0) {
      onError(result.message);
      return;
    }

    $('#conversionResult .operation').text('Queued');
    
    setTimeout(function() {tomp3org.waitForJob(result.data.jobHandle).done(function() {
        
        var onMetadata = function(result) {
          tomp3org.initResulOptions(result);

          $('#conversionResult .title').fadeOut(tomp3org.fxDuration, function() {
            $('#conversionResult .title').text(result.data.title).attr('title', result.data.title)
            .fadeIn(tomp3org.fxDuration);

            // Set author
            $('#conversionResult .info').text(result.data.author
              + ' (' + result.data.duration + ')');
            $('#conversionResult .info').fadeIn(tomp3org.fxDuration);

            // Set description
            $('#conversionResult .description div').text(result.data.description);
            $('#conversionResult .description').fadeIn(tomp3org.fxDuration);

            // Title width
            $('#conversionResult .title').css(
              {width: $('#conversionResult .titleParent').width()});

            // Image
            $('#conversionResult .thumbnailImg').
              attr('src', result.data.thumbnailUrl).load(function() {
                $('#conversionResult .thumbnailImg').fadeIn(tomp3org.fxDuration * 2);
              })
          })
        };
        
        var onProgress = function(result) {
          var percent = Math.round((result.data.nominator
            / result.data.denominator) * 100);
          
          if (isNaN(percent)) percent = 0;

          if (percent < 100)
            $('#conversionResult .operation')
              .text('Downloaded ' + percent + '%');
          else
            $('#conversionResult .operation')
              .text('Converting');
        };
        
        var onResult = function(result) {
          tomp3org.stopProgressAnimation();
          
          $('#conversionResult .progress').fadeOut(tomp3org.fxDuration, function() {
            
            // Set download URL
            $('#conversionResult .result a').attr('href',
              'http://' + result.data.workerHost + 
                '/download/' + result.data.downloadToken)

            $('#conversionResult .result').fadeIn(tomp3org.fxDuration, function(){
              $('#buttonBack').removeClass('disabled');
              tomp3org.toggleResultOptions('share');
            });
          });
        };
        
        setTimeout(function(){
          tomp3org.trackJob(result.data.jobHandle,
            onMetadata, onProgress, onResult, onError);
        }, 1000);

      }).fail(function(message){onError(message)})}, 1000);
  });
}

/**
* Init result options pages
*/
tomp3org.initResulOptions = function(metadataResult)
{
  // BBCode link
  $('#codeFieldBbCode').val('[url=http://tomp3.org/#'+
    metadataResult.data.linkDescriptor + ']' + metadataResult.data.title + '[/url]')

  // HTML link
  $('#codeFieldHtml').val('<a href="http://tomp3.org/#'+
    metadataResult.data.linkDescriptor + '">' + metadataResult.data.title + '</a>');

  // Direct link
  $('#codeFieldDirect').val('http://tomp3.org/#'+
    metadataResult.data.linkDescriptor);

  $('#codeResultOptions .field input').click(function() {this.focus();this.select()});
}

/**
 * Wait for job start
 */
tomp3org.waitForJob = function(jobHandle)
{
  var d = new $.Deferred();

  var f = function() {
    tomp3org.callApi('Conversion.isJobStarted', {jobHandle : jobHandle})
      .done(function(result) {
        if (result.error != 0) d.reject('Failed to start conversion');
        else if (result.data == false) setTimeout(f, 3000);
        else d.resolve();
      })
      .fail(function() {d.reject('Failed to call Conversion.isJobStarted API')});
  };

  f();

  return d;
}

/**
 * Track started job
 */
tomp3org.trackJob = function(
  jobHandle,
  onMetadata,
  onProgress,
  onResult,
  onError)
{
  var metadataArrived = false;
  var resultArrived = false;
  var jobError = false;

  var metadataF = function(){
    tomp3org.callApi('Conversion.getJobMetadata', {jobHandle : jobHandle})
      .done(function(result) {
        if (result.error == 0) {
          onMetadata(result);
        }})
      .fail(function() {
        onError('#1: Error fetching job metadata')
      });
  }

  var resultF = function(){
    tomp3org.callApi('Conversion.getJobResult', {jobHandle : jobHandle})
      .done(function(result) {
        if (result.error == 0) {
          if (result.data.error == 0)
            onResult(result);
          else {
            jobError = true;
            onError('Error #0: ' + result.data.message);
          }
        }
        else onError('Error #2: Job result not found')
    });
  }

  var trackF = function() {
    if (jobError) return;

    tomp3org.callApi('Conversion.getJobStatus', {jobHandle : jobHandle})
      .done(function(result){
        
        // Metadata
        if (result.data.hasMetadata && !metadataArrived) {
          metadataF();
          metadataArrived = true;
        }

        // Result
        if (!result.data.isRunning && !resultArrived) {
          resultF();
          resultArrived = true;
        }

        // Progress
        if (result.data.isRunning) onProgress(result);

        // Repeat
        if ((result.data.hasMetadata && !metadataArrived) || !resultArrived || result.data.isRunning)
          setTimeout(trackF, 3000);
      })
    .fail(function() {onError('Errro #3: Failed fetching job status')});
  };

  trackF();
}

/**
 * Call JSON api
 */
tomp3org.callApi = function(api, data)
{
  var apiUrl;
  var methodUri = '';
  var d = new $.Deferred();

  // Create url for API call

  apiUrl = api.split('.');
  apiUrl = '/api/' + apiUrl[0] + '/' + apiUrl[1] + '?' + tomp3org.getDebugString();

  // Make API request

  $.ajaxSetup({cache: false});
  $.getJSON(apiUrl, data)
    .success(function(result){d.resolve(result)})
    .error(function(){d.reject()});

  return d;
}

/**
 * Show page
 */
tomp3org.showPage = function(pageId)
{
  var duration = tomp3org.fxDuration;
  var scroll = false;
  var overflow;
  var d = new $.Deferred();
  
  tomp3org.pageId = tomp3org.pageId || 'page1';

  if (pageId != tomp3org.pageId)
  {
    var oldPageId = tomp3org.pageId;
    
    tomp3org.pageId = pageId;
    
    // Change overflow
    
    if (document.body.clientHeight !=
      document.body.offsetHeight) scroll = true;

    if (!scroll)
    {
      overflow = $('body').css('overflow');
      $('body').css('overflow', 'hidden');
    }

    // Show requested page

    $('#' + pageId).fadeIn(duration);

    // Hide current page

    $('#' + oldPageId).fadeOut(duration);

    // Adjust height
    
    $('#contentPages').animate(
      {'height' : $('#' + pageId).height()}, duration, null,
      function(){if (!scroll) $('body').css('overflow', overflow);d.resolve();}
    );
  } else d.resolve();

  return d;
}

tomp3org.adjustPageHeight = function()
{
  
}

tomp3org.goBack = function()
{
  // Reset UI

  $('#inputUrl').val('');

  tomp3org.showPage('page1').then(function(){
    tomp3org.toggleResultOptions(tomp3org._resultOptionsShown, true);
    $('#conversionResult .info').hide();
    $('#conversionResult .description').hide();
    $('#conversionResult .result').hide();
    $('#conversionResult .progress').show();
    $('#buttonBack').addClass('disabled');
    $('#conversionResult .error').hide();
    $('#conversionResult .thumbnailImg').
      attr('src', '/images/pixel.gif');
    $('#inputUrl').focus();
  });
}

// Executed at page load
$(tomp3org.init);

// Executed at page unload
$(window).unload(function(){});