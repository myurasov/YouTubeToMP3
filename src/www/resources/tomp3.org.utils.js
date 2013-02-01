/**
 * Background-position animation plug-in for jQuery
 * @author Alexander Farkas
 * @author Mikhail Yurasov <me@yurasov.me> Added support for relative property values
 * v. 1.03
 */
(function($) {
  $.extend($.fx.step,{
    backgroundPosition: function(fx) {
      if (fx.state === 0 && typeof fx.end == 'string') {
        var start = $.curCSS(fx.elem, 'backgroundPosition');
        start = toArray(start);
        fx.start = [start[0],start[2]];
        var end = toArray(fx.end, start);
        fx.end = [end[0],end[2]];
        fx.unit = [end[1],end[3]];
      }

      var nowPosX = [];
      nowPosX[0] = ((fx.end[0] - fx.start[0]) * fx.pos) + fx.start[0] + fx.unit[0];
      nowPosX[1] = ((fx.end[1] - fx.start[1]) * fx.pos) + fx.start[1] + fx.unit[1];
      fx.elem.style.backgroundPosition = nowPosX[0]+' '+nowPosX[1];

      function toArray(strg, start){
        var x, y;
        start = start || [0, 0, 0, 0];

        strg = strg.replace(/left|top/g,'0px');
        strg = strg.replace(/right|bottom/g,'100%');
        strg = strg.replace(/([0-9\.]+)(\s|\)|$)/g,"$1px$2");
        var res = strg.match(/([+-]=)?(-?[0-9\.]+)(px|\%|em|pt)\s([+-]=)?(-?[0-9\.]+)(px|\%|em|pt)/);

        if (res[1] == '-=') x = start[0] - parseFloat(res[2], 10);
        else if (res[1] == '+=') x = start[0] + parseFloat(res[2], 10);
        else x = parseFloat(res[2], 10);
        
        if (res[4] == '-=') y = start[2] - parseFloat(res[5], 10);
        else if (res[4] == '+=') y = start[2] + parseFloat(res[5], 10);
        else y = parseFloat(res[5], 10);

        return [x, res[3], y, res[6]];
      }
    }
  });
})(jQuery);