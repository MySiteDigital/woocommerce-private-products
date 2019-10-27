/**
 * Main WPP Script
 * 
 * @author Willon Nava
 */
(function($) {

    "use strict";
    
    var wpp = {
        
        /**
         * Initialize Slim-Select
         */
        init: function() {
            this.selectId = "#restrict-user-list";
            this.selectElement = $(this.selectId);
            this.select = null;
            this.spawn_slim_select = this.spawn_slim_select.bind( this );

            try {
                this.spawn_slim_select();
            } catch (e) {}
        },

        spawn_slim_select: function() {

            this.select = new SlimSelect({
                select: this.selectId,
                searchText: this.selectElement.data('searchtext'),
                searchPlaceholder: this.selectElement.data('searchplaceholder'),
            });
        }
    }

    wpp.init();
})(jQuery);
