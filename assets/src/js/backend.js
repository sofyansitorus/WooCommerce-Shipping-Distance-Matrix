var wcsdmBackend = {
	init: function(params){
		wcsdmTableRates.init(params);
	}
};

$(document).ready(function () {
	wcsdmBackend.init(wcsdm_params);
});
