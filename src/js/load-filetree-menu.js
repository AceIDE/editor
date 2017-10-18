function display_context_menu(e) {
    e.preventDefault();
    e.stopPropagation();

    var $this       = jQuery(this),
        $parent     = $this.parent()
        left        = e.offsetX,
        is_file     = $parent.hasClass("file"),
        is_dir      = $parent.hasClass("directory"),
        is_zip      = $parent.hasClass("ext_zip"),
        is_editable = is_file &&
            (
                $parent.hasClass("ext_afp") || $parent.hasClass("ext_afpa") || $parent.hasClass("ext_asp") ||
                $parent.hasClass("ext_aspx") || $parent.hasClass("ext_c") || $parent.hasClass("ext_cfm") ||
                $parent.hasClass("ext_cgi") || $parent.hasClass("ext_cpp") || $parent.hasClass("ext_css") ||
                $parent.hasClass("ext_h") || $parent.hasClass("ext_htm") || $parent.hasClass("ext_html") ||
                $parent.hasClass("ext_js") || $parent.hasClass("ext_lasso") || $parent.hasClass("ext_log") ||
                $parent.hasClass("ext_php") || $parent.hasClass("ext_pl") || $parent.hasClass("ext_py") ||
                $parent.hasClass("ext_rb") || $parent.hasClass("ext_rbx") || $parent.hasClass("ext_rhtml") ||
                $parent.hasClass("ext_ruby") || $parent.hasClass("ext_sql") || $parent.hasClass("ext_txt") ||
                $parent.hasClass("ext_vb") || $parent.hasClass("ext_xml") || $parent.hasClass("ext_error_log")
            );

    left += parseInt($parent.css('paddingLeft'), 10);

    // Create menu
    var $menu = jQuery("<ul class='aceide-context-menu'>").css({
        'left':left + 'px',
        'top':e.offsetY + 'px'
    });


    // Add Menu Items
    if (is_editable) {
        $menu.append(function() {
            var $item = jQuery("<li>")
                    .attr("class", "menu-item-edit")
                    .text("Edit")
                    .on("click", function() {
                        $this.parent().addClass('wait');
						aceide_set_file_contents($this.attr('rel'), function(){
							$this.parent().removeClass('wait');
						});
                    });

            return $item;
        });
    }

        $menu.append(function() {
            var $item = jQuery("<li>")
                    .attr("class", "menu-item-rename")
                    .text("Rename")
                    .on("click", function() {
                        aceide_rename_file($this.attr("rel"));
                    });

            return $item;
        });

        $menu.append(function() {
            var $item = jQuery("<li>")
                    .attr("class", "menu-item-delete")
                    .text("Delete")
                    .on("click", function() {
                        aceide_delete_file($this.attr("rel"));
                    });

            return $item;
        });

    if (is_dir) {
        $menu.append(function() {
            var $item = jQuery("<li>")
                    .attr("class", "menu-item-upload")
                    .text("Upload")
                    .on("click", function() {
                        aceide_upload_file($this.attr("rel"));
                    });

            return $item;
        });
    } else {
        $menu.append(function() {
            var $item = jQuery("<li>")
                    .attr("class", "menu-item-download")
                    .text("Download")
                    .on("click", function() {
                        aceide_download_file($this.attr("rel"));
                    });

            return $item;
        });
    }

    if (!is_zip) {
        $menu.append(function() {
            var $item = jQuery("<li>")
                    .attr("class", "menu-item-zip")
                    .text("Zip")
                    .on("click", function() {
                        if (confirm("We are going to zip this file or directory:\n" + $this.attr("rel") + "\n\nContinue?")) {
                            aceide_zip_file($this.attr("rel"));
                        }
                    });

            return $item;
        });
    } else {
        $menu.append(function() {
            var $item = jQuery("<li>")
                    .attr("class", "menu-item-unzip")
                    .text("Unzip")
                    .on("click", function() {
                        if (confirm("We are going to unzip this zip file:\n" + $this.attr("rel") + "\n\nContinue?")) {
                            aceide_unzip_file($this.attr("rel"));
                        }
                    });

            return $item;
        });
    }

    // Event handlers:
    function destroy_menu() {
        jQuery(document).unbind("mousedown", exit_menu)
                        .unbind("click", exit_menu_onclick);
        jQuery(window).unbind("blur", exit_menu_onblur);
        jQuery($menu).unbind("contextmenu");
        $menu.hide(200, function() {
            $menu.remove();
        });
    }

    function exit_menu(e) {
        var src_element = e.target || e.srcElement;

        if (!jQuery(src_element).closest('.aceide-context-menu').get().length)
            destroy_menu();
    }
    function exit_menu_onclick(e) {
        var src_element = e.target || e.srcElement;

        if (!jQuery(src_element).hasClass('aceide-context-menu'))
            destroy_menu();
    }
    function exit_menu_onblur() {
        destroy_menu();
    }

    // The mousedown event only triggers when the menu is NOT clicked.
    // When the menu IS clicked, the "click" event destroys the menu.
    // This allows the menu to wait for mouseup before being destroyed.
    jQuery(document).bind("mousedown", exit_menu)
                    .bind("click", exit_menu_onclick);
    jQuery(window).bind("blur", exit_menu_onblur);

    // We don't want the normal context menu coming up on top of ours.
    $menu.bind("contextmenu", function(e) {
        e.preventDefault();
    });

    $menu.appendTo($parent).show(200);
}

// rename file
function aceide_rename_file(file, callback_func) {
    var folder = file.replace(/\/[^\/]*?\/?$/, '/'),
        filename = file.replace(folder, ''),
        new_name = prompt("What would you like to change the name to", filename),
        data = { action: 'aceide_rename_file', filename: file, newname: new_name, _wpnonce: jQuery("#_wpnonce").val(),  _wp_http_referer: jQuery('#_wp_http_referer').val() };

    // User cancelled
    if (new_name === null)
        return;

    jQuery.post(aceajax.url, data, function(response) {
        // If we are adding to wp-content, make sure we refresh the whole tree.
        if (jQuery("ul.jqueryFileTree a[rel='"+ folder +"']").length == 0) {
            the_filetree();
        } else {
            jQuery("ul.jqueryFileTree a[rel='"+ folder +"']").click().click();
        }
    });
}

// delete file
function aceide_delete_file(file, callback_func) {
    var folder = file.replace(/\/[^\/]*?\/?$/, '/'),
        filename = file.replace(folder, ''),
        data = { action: 'aceide_delete_file', filename: file, _wpnonce: jQuery("#_wpnonce").val(),  _wp_http_referer: jQuery('#_wp_http_referer').val() };

    if ( !confirm("Are you sure you wish to delete " + filename + "?") )
        return;

    jQuery.post(aceajax.url, data, function(response) {
        // If we are adding to wp-content, make sure we refresh the whole tree.
        if (jQuery("ul.jqueryFileTree a[rel='"+ folder +"']").length == 0) {
            the_filetree();
        } else {
            jQuery("ul.jqueryFileTree a[rel='"+ folder +"']").click().click();
        }
    });
}

// upload file
function aceide_upload_file(server_destination, callback_func) {
    var data = {  _wp_http_referer: jQuery('#_wp_http_referer').val() };

    jQuery("<input>").attr({
        type: "file",
        multiple: "multiple"
    }).bind("change", function(e) {
        var files = e.target.files || e.dataTransfer.files,
            formdata = new FormData();

        for (var i = 0; i < files.length; i++) {
            formdata.append(i, files[i]);
        }

        formdata.append('action', 'aceide_upload_file');
        formdata.append('destination', server_destination);
        formdata.append('_wpnonce', jQuery("#_wpnonce").val());
        formdata.append('_wp_http_referer', jQuery("#_wp_http_referer").val());

        // We need to use XMLHttpRequest instead of jQuery.ajax here
        var xhr = new XMLHttpRequest();

        xhr.open('POST', aceajax.url, true);
        xhr.onreadystatechange = function(e) {
            if (this.readyState === 4) {
                if (this.status === 200) {
                    if (this.responseText === '') {
                        // If we are adding to wp-content, make sure we refresh the whole tree.
                        if (jQuery("ul.jqueryFileTree a[rel='"+ server_destination +"']").length == 0) {
                            the_filetree();
                        } else {
                            jQuery("ul.jqueryFileTree a[rel='"+ server_destination +"']").click().click();
                        }
                    } else {
                        alert(this.responseText);
                    }
                } else {
                    alert("HTTP Error " + this.status + "\n\n" + this.statusText);
                }
            }
        };
        xhr.send(formdata);
    }).click();
}

// download file
function aceide_download_file(file, callback_func) {
    var filename = file.replace(/.*?([^\\\/]+?)$/, '$1'),
        data = { action: 'aceide_download_file', filename: file, _wpnonce: jQuery("#_wpnonce").val(), _wp_http_referer: jQuery('#_wp_http_referer').val() };

//    jQuery("a[rel='"+file+"']").parent().addClass("wait");

    jQuery.post(aceajax.url, data, function(response) {
        var $iframe = jQuery("<iframe>");

        $iframe.css("display","none").appendTo(document.body);
        $iframe.get(0).contentWindow.document.write(
            '<form action="'+aceajax.url+'" method="POST"><input type="hidden"' +
            ' name="action" value="aceide_download_file" /><input type=' +
            '"hidden" name="filename" value="'+file+'" /><input type="hidden"' +
            ' name="_wpnonce" value="'+jQuery("#_wpnonce").val()+'" /><input' +
            ' type="hidden" name="_wp_http_referer" value="' +
            jQuery('#_wp_http_referer').val() + '" /></form>'
        );
        $iframe.get(0).contentWindow.document.forms[0].submit();
/*
        var url = window.URL.createObjectURL(new Blob([response])),

        jQuery("<a>").attr({
            href: url,
            download: filename
        }).css({
            display: "none"
        }).bind("click", function() {
            // This is so we don't revokeObjectURL before the file download starts
            jQuery(window).on("focus", function revoke_on_focus() {
                jQuery(window).off("focus", revoke_on_focus);
                window.URL.revokeObjectURL(url);
                jQuery("a[rel='"+file+"']").parent().removeClass("wait");
            });
        })

        // jQuery's click trigger doesn't work. Put this on native javascript
            .get(0).click();
*/
    });
}

// zip given file
function aceide_zip_file(file, callback_func) {
    var folder = file.replace(/\/[^\/]*?\/?$/, '/'),
        data = { action: 'aceide_zip_file', filename: file, _wpnonce: jQuery("#_wpnonce").val(),  _wp_http_referer: jQuery('#_wp_http_referer').val() };

    jQuery.post(aceajax.url, data, function(response) {
        if (response.length) {
            alert(response);
        }

        // If we are adding to wp-content, make sure we refresh the whole tree.
        if (jQuery("ul.jqueryFileTree a[rel='"+ folder +"']").length == 0) {
            the_filetree();
        } else {
            jQuery("ul.jqueryFileTree a[rel='"+ folder +"']").click().click();
        }

        callback_func && callback_func();
    });
}

// unzip given file
function aceide_unzip_file(file, callback_func) {
    var folder = file.replace(/\/[^\/]*?\/?$/, '/'),
        data = { action: 'aceide_unzip_file', filename: file, _wpnonce: jQuery("#_wpnonce").val(),  _wp_http_referer: jQuery('#_wp_http_referer').val() };

    jQuery.post(aceajax.url, data, function(response) {
        if (response.length) {
            alert(response);
        }

        // If we are adding to wp-content, make sure we refresh the whole tree.
        if (jQuery("ul.jqueryFileTree a[rel='"+ folder +"']").length == 0) {
            the_filetree();
        } else {
            jQuery("ul.jqueryFileTree a[rel='"+ folder +"']").click().click();
        }

        callback_func && callback_func();
    });
}

