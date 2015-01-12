"use strict";

var DL = DL || {};

DL.survey_num	= SURVEY_NUM;
DL.site_num		= SITE;
DL.aicode		= AICODE;

DL.serialize = function (o) {
    var k,s=[];
    for (k in o) {
        s[s.length] = encodeURIComponent(k) + "=" + encodeURIComponent((o[k]||'').toString().replace(/\r?\n/g, "\r\n"));
    }
    return s.join("&").replace(/%20/g, "+");
};

DL.scr = function (s,i,d) {
    var a = 'script',
        d = d || document,
        e = d.createElement(a),
        t = d.getElementsByTagName(a)[0] || d.body.firstChild;
    e.src = s;
    i&&(e.id=i);
    return t.parentNode.insertBefore(e,t);
};

// One day this object should be optimized to use getters and setters,
// but this will be possible only when there's no <=IE8 anymore
DL.UID = new function () {
    var UID = null;

    this.get = function () {
        return UID;
    }
    this.set = function (value) {
        UID = value;
        this.onupdate||this.onupdate.call(window, UID);
    }
    this.onupdate = null;
};

(function (data) {
    (new Image).src='https://SERVER_NAME/adsc/dSURVEY_NUM/SITE/AICODE/adscout.php?ord='+(new Date).getTime();

    try {
        var path = 'https://SERVER_NAME/adscgen/escout/';

        var iframe = document.createElement('iframe');
        iframe.style.cssText ='position:absolute; width:0; height:0;';
        iframe.setAttribute('frameBorder', 0);
        iframe.setAttribute('src', path+'iframe.html#'+DL.serialize(data));

        document.body.appendChild(iframe);

        if (document.getElementsByTagName('iframe').length < 1) {
            DL.UID.onupdate = function (UID) {
                DL.scr(path+'escout.php?UID='+UID+'&'+DL.serialize(data));
                (new Image).src='https://SERVER_NAME/adscgen/log_error.php?errcode=13&details=d'+DL.survey_num+'/'+DL.site_num+'/'+DL.aicode+'/'+UID;
            }
            DL.scr(path+'escout.php');
            (new Image).src='https://SERVER_NAME/adscgen/log_error.php?errcode=12&details=d'+DL.survey_num+'/'+DL.site_num+'/'+DL.aicode;
        }
    } catch (e) {
        (new Image).src='https://SERVER_NAME/adscgen/log_error.php?errcode=11&details=d'+DL.survey_num+'/'+DL.site_num+'/'+DL.aicode+'/'+e.name+'/'+e.message;
    }
})({
    survey_num: DL.survey_num,
    site_num: DL.site_num,
    aicode: DL.aicode
});

DL_KTAG