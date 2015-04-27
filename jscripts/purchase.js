// Wysłanie formularza zakupu
$(document).delegate("#form_purchase", "submit", function (e) {
    e.preventDefault();
});

var purchase_data, purchase_sign;

$(document).delegate("#go_to_payment", "click", function () {
    if (loader.blocked)
        return;

    loader.show();
    $.ajax({
        type: "POST",
        url: "jsonhttp.php",
        data: $("#form_purchase").serialize() + "&action=validate_purchase_form",
        complete: function () {
            loader.hide();
        },
        success: function (content) {
            $(".form_warning").remove(); // Usuniecie komunikatow o blednym wypelnieniu formualarza

            if (!(jsonObj = json_parse(content)))
                return;

            // Wyświetlenie błędów w formularzu
            if (jsonObj.return_id == "warnings") {
                $.each(jsonObj.warnings, function (name, text) {
                    var id = $("#form_purchase [name=\"" + name + "\"]:first");
                    id.parent().append(text);
                    id.effect("highlight", 1000);
                });
            }
            else if (jsonObj.return_id == "validated") {
                purchase_data = jsonObj.data; // Tak musi byc, bo inaczej nie bedzie dzialac
                purchase_sign = jsonObj.sign;
                // Zmiana zawartosci okienka content na płatność za zakupy
                fetch_data("payment_form", false, {data: purchase_data, sign: purchase_sign}, function (message) {
                    $("#content").html(message);
                });
            }
            else if (!jsonObj.return_id) {
                show_info(lang['sth_went_wrong'], false);
                return;
            }

            // Wyświetlenie zwróconego info
            if (typeof(jsonObj.length) !== 'undefined') show_info(jsonObj.text, jsonObj.positive, jsonObj.length);
            else show_info(jsonObj.text, jsonObj.positive);
        },
        error: function (error) {
            infobox.show_info("Wystąpił błąd podczas wysyłania formularza zakupu.", false);
        }
    });
});

// Pokaż pełny opis usługi
$(document).delegate("#show_service_desc", "click", function () {
    loader.show();
    $.ajax({
        type: "POST",
        url: "jsonhttp.php",
        data: {
            action: "get_service_long_description",
            service: $("#form_purchase [name=service]").val()
        },
        complete: function () {
            loader.hide();
        },
        success: function (content) {
            window_info.create('80%', '80%', content);
        },
        error: function (error) {
            show_info("Wystąpił błąd podczas wczytywania pełnego opisu.", false);
        }
    });
});