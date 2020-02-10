jQuery(function () {
    $div = jQuery('.plugin_booking');
    if (!$div.length) return;

    $div.load(
        DOKU_BASE + 'lib/exe/ajax.php',
        {
            call: 'plugin_booking',
            id: JSINFO['id'],
        }
    ).on('submit', function (e) {
        e.preventDefault();

        param = jQuery(e.target).serializeArray();
        param.push({name: 'call', value: 'plugin_booking'});
        param.push({name: 'id', value: JSINFO['id']});
        param.push({name: 'do', value: 'book'});

        $div.load(
            DOKU_BASE + 'lib/exe/ajax.php',
            param
        );
    }).on('click', '.cancel', function (e) {
        e.preventDefault();
        $div.load(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_booking',
                do: 'cancel',
                id: JSINFO['id'],
                at: e.target.href.split('#')[1],
            }
        );
    });
});

