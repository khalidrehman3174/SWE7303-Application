/* Smartsupp Live Chat Script */
var _smartsupp = _smartsupp || {};
_smartsupp.key = '5a1e6260a06a9b20690fb4f544508708f20617ef';
_smartsupp.hideWidget = true;
window.smartsupp || (function (d) {
    var s, c, o = smartsupp = function () { o._.push(arguments) }; o._ = [];
    s = d.getElementsByTagName('script')[0]; c = d.createElement('script');
    c.type = 'text/javascript'; c.charset = 'utf-8'; c.async = true;
    c.src = 'https://www.smartsuppchat.com/loader.js?'; s.parentNode.insertBefore(c, s);
})(document);

// Force hide on specific events to prevent reappearance
smartsupp('on', 'startup', function () {
    smartsupp('widget:hide');
});

smartsupp('on', 'chat:closed', function () {
    smartsupp('widget:hide');
});
