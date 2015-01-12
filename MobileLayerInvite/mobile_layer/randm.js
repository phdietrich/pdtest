(function() {
    var d=document, w=window;
    function z() {
        if (w!=top) {
            try{
                if (top.document) {
                    var dls=top.document.createElement('script');
                    dls.id="dls";
                    dls.type="text/javascript";
                    dls.src="http://SERVER_NAME/adsc/dSURVEY_NUM/SITE/AICODE/randm.js";
                    top.document.body.appendChild(dls);
                    return;
                }
            } catch(e) {}
            return;
        }
        try {
            var dlif = d.createElement('iframe');
            dlif.style.cssText ='display:none; width:0px; height:0px;';
            dlif.id = "dlif";
            dlif.name = "dlif";
            dlif.src = "http://SERVER_NAME/adsc/dSURVEY_NUM/SITE/AICODE/mobile.php?ord="+Math.random()*100;
            d.body.appendChild(dlif);
        } catch (e) { }
    }

    var eMethod = (w.addEventListener) ? "addEventListener" : "attachEvent";
    var msgEvt = (eMethod == "attachEvent") ? "onmessage" : "message";
    var evtHandler = w[eMethod];
    evtHandler(msgEvt, function(e) {
        if (e.origin.indexOf("SERVER_NAME") < 0) return;
        if ("invite" == e.data) {
            var dls=d.createElement('script');
            dls.id="dls";
            dls.type="text/javascript";
            dls.src="http://SERVER_NAME/adscgen/m_layer.php?survey_num=SURVEY_NUM&site=SITE&code=AICODE&sub=DYNAMICLINK_DOMAIN";
            d.body.appendChild(dls);
        }
    },false);

    if (IMMEDIATE) {
        z();
    } else if (w.addEventListener) {
        w.addEventListener("load", z, false);
    } else if (w.attachEvent) {
        w.attachEvent("onload", z);
    }
})();

