jQuery( function( $ ) {
    var $swi = $('.swi-connections');
    var $swiButton = $('.swi-button-register');

    $swiButton.click( function() {
        $.ajax( {
            url: Ajax.adminAjax,
            type: 'POST',
            data: {
                'action' : 'SendJSON'
            },
            success:function(response) {
                console.log(response);
                if (response['status'] === 'success') {
                    window.location.assign(response['data']);
                } else {
                    alert("We had a problem accessing the API. Please, contact SmartrMail Support");
                }
            }
        } );
    } );
} );
