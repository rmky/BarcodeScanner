<?php
namespace exface\BarcodeScanner\Actions\Scanners;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\BarcodeScanner\Actions\AbstractScanAction;

class QuaggaJsScanner extends AbstractJsScanner
{
    private $use_file_upload = false;
    
    private $use_camera = false;
    
    private $switch_camera = false;
    
    private $viewfinder_width = '640';
    
    private $viewfinder_height = '480';
    
    private $barcode_types = 'ean, ean_8';
    
    private $onScanJsScanner = null;
    
    public function getUseFileUpload()
    {
        return $this->use_file_upload;
    }
    
    /**
     * Set to TRUE to enable uploading images with barcodes to trigger the action - FALSE by default.
     *
     * This option strongly depends on the device and the facade used.
     *
     * @uxon-property use_file_upload
     * @uxon-type boolean
     *
     * @param boolean $value
     * @return QuaggaJsScanner
     */
    public function setUseFileUpload($value) : QuaggaJsScanner
    {
        $this->use_file_upload = BooleanDataType::cast($value);
        return $this;
    }
    
    public function getUseCamera()
    {
        return $this->use_camera;
    }
    
    /**
     * Set to TRUE to enable scanning barcodes with the built-in camera of your device - FALSE by default.
     *
     * This option strongly depends on the device and the facade used.
     *
     * @uxon-property use_camera
     * @uxon-type boolean
     *
     * @param boolean $value
     * @return QuaggaJsScanner
     */
    public function setUseCamera($value) : QuaggaJsScanner
    {
        $this->use_camera = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     * Returns a comma separated list of allowed barcode types or NULL if all types are allowed.
     *
     * @return string
     */
    public function getBarcodeTypes()
    {
        return $this->barcode_types;
    }
    
    /**
     * Specifies a list of allowed barcode types (other barcodes will be ignored).
     *
     * @uxon-property barcode_types
     * @uxon-type string
     *
     * @param string $value
     * @return QuaggaJsScanner
     */
    public function setBarcodeTypes($value) : QuaggaJsScanner
    {
        $this->barcode_types = $value;
        return $this;
    }
    
    public function getSwitchCamera()
    {
        return $this->switch_camera;
    }
    
    public function setSwitchCamera($value)
    {
        $this->switch_camera = BooleanDataType::cast($value);
        return $this;
    }
    
    public function getCameraViewfinderWidth()
    {
        return $this->viewfinder_width;
    }
    
    public function setCameraViewfinderWidth($value)
    {
        $this->viewfinder_width = $value;
        return $this;
    }
    
    public function getCameraViewfinderHeight()
    {
        return $this->viewfinder_height;
    }
    
    public function setCameraViewfinderHeight($value)
    {
        $this->viewfinder_height = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\BarcodeScanner\Interfaces\JsScannerWrapperInterface::buildJsScannerInit()
     */
    public function buildJsScannerInit(FacadeInterface $facade) : string
    {
        $result = '';
        $button = $facade->getElement($this->getScanAction()->getWidgetDefinedIn());
        
        $readers = explode(',', $this->getBarcodeTypes());
        for ($i = 0; $i < count($readers); $i ++) {
            $readers[$i] = trim($readers[$i]) . '_reader';
        }
        $readers_init = json_encode($readers);
        
        $camera = $this->getSwitchCamera() ? 'user' : 'environment';
        
        if ($this->getUseFileUpload()) {
            $result = <<<JS
            
$(function() {
	$('#{$button->getId()}').after($('<input style="visibility:hidden; display:inline; width: 0px;"type="file" id="{$button->getId()}_file" accept="image/*;capture=camera"/>'));
	
    var App = {
        init: function() {
            App.attachListeners();
        },
        config: {
            reader: "ean",
            length: 10
        },
        attachListeners: function() {
            var self = this;
            
            $("#{$button->getId()}_file").on("change", function(e) {
                if (e.target.files && e.target.files.length) {
                    App.decode(URL.createObjectURL(e.target.files[0]));
                }
            });
            
            $(".controls button").on("click", function(e) {
                var input = document.querySelector(".controls input[type=file]");
                if (input.files && input.files.length) {
                    App.decode(URL.createObjectURL(input.files[0]));
                }
            });
            
            $(".controls .reader-config-group").on("change", "input, select", function(e) {
                e.preventDefault();
                var target = $(e.target),
                    value = target.attr("type") === "checkbox" ? target.prop("checked") : target.val(),
                    name = target.attr("name"),
                    state = self._convertNameToState(name);
                    
                console.log("Value of "+ state + " changed to " + value);
                self.setState(state, value);
            });
            
        },
        _accessByPath: function(obj, path, val) {
            var parts = path.split('.'),
                depth = parts.length,
                setter = (typeof val !== "undefined") ? true : false;
                
            return parts.reduce(function(o, key, i) {
                if (setter && (i + 1) === depth) {
                    o[key] = val;
                }
                return key in o ? o[key] : {};
            }, obj);
        },
        _convertNameToState: function(name) {
            return name.replace("_", ".").split("-").reduce(function(result, value) {
                return result + value.charAt(0).toUpperCase() + value.substring(1);
            });
        },
        detachListeners: function() {
            $(".controls input[type=file]").off("change");
            $(".controls .reader-config-group").off("change", "input, select");
            $(".controls button").off("click");
            
        },
        decode: function(src) {
            var self = this,
                config = $.extend({}, self.state, {src: src});
			{$button->buildJsBusyIconShow()}
            setTimeout(function() {
			    {$button->buildJsBusyIconHide()}
			}, 5000);
            Quagga.decodeSingle(config, function(result) { $(document).scannerDetector(result.codeResult.code); {$button->buildJsBusyIconHide()}});
        },
        setState: function(path, value) {
            var self = this;
            
            if (typeof self._accessByPath(self.inputMapper, path) === "function") {
                value = self._accessByPath(self.inputMapper, path)(value);
            }
            
            self._accessByPath(self.state, path, value);
            
            console.log(JSON.stringify(self.state));
            App.detachListeners();
            App.init();
        },
        inputMapper: {
            inputStream: {
                size: function(value){
                    return parseInt(value);
                }
            },
            numOfWorkers: function(value) {
                return parseInt(value);
            },
            decoder: {
                readers: function(value) {
                    return [value + "_reader"];
                }
            }
        },
        state: {
            inputStream: {
                size: 800
            },
            locator: {
                patchSize: "medium",
                halfSample: false
            },
            numOfWorkers: 8,
            decoder: {
                readers: {$readers_init}
            },
            locate: true,
            src: null
        }
    };
    
    App.init();
});

JS;
        } elseif ($this->getUseCamera()) {
            $dialog = <<<JS
<div class="modal" id="{$button->getId()}_scanner">\
	<style>\
		#interactive.viewport {position: relative;}\
		#interactive.viewport > canvas, #interactive.viewport > video { max-width: 100%; width: 100%;}\
		canvas.drawing, canvas.drawingBuffer {position: absolute;left: 0;top: 0;}\
	</style>\
	<div class="modal-dialog modal-lg">\
		<div class="modal-content">\
			<div class="modal-header">\
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>\
				<h4 class="modal-title">Scanner</h4>\
			</div>\
			<div class="modal-body" style="text-align:center;">\
				<div id="interactive" class="viewport"></div>\
			</div>\
			<div class="modal-footer">\
        		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>\
      		</div>\
		</div><!-- /.modal-content -->\
	</div><!-- /.modal-dialog -->\
</div><!-- /.modal -->\
JS;
            $result = <<<JS
            
$(function() {
	$('body').append('{$dialog}');
	
	$("#{$button->getId()}").on("click", function(e) {
       $('#{$button->getId()}_scanner').modal('show');
		Quagga.init({
				inputStream: {
	                type : "LiveStream",
	                constraints: {
	                    width: {$this->getCameraViewfinderWidth()},
	                    height: {$this->getCameraViewfinderHeight()},
	                    facingMode: "{$camera}"
	                }
	            },
	            locator: {
	                patchSize: "medium",
	                halfSample: true
	            },
	            numOfWorkers: 4,
	            decoder: {
	            	readers: [{"format":"ean_reader","config":{}}]
	            },
	            locate: true
			},
			function(err) {
				if (err) {
					console.log(err);
					return;
				}
				Quagga.start();
			}
		);
    });
    
    $('#{$button->getId()}_scanner').on('hide.bs.modal', function(){
    	if (Quagga){
    		Quagga.stop();
    	}
    });
    
	Quagga.onProcessed(function(result) {
        var drawingCtx = Quagga.canvas.ctx.overlay,
            drawingCanvas = Quagga.canvas.dom.overlay;
            
        if (result) {
            if (result.boxes) {
                drawingCtx.clearRect(0, 0, parseInt(drawingCanvas.getAttribute("width")), parseInt(drawingCanvas.getAttribute("height")));
                result.boxes.filter(function (box) {
                    return box !== result.box;
                }).forEach(function (box) {
                    Quagga.ImageDebug.drawPath(box, {x: 0, y: 1}, drawingCtx, {color: "green", lineWidth: 2});
                });
            }
            
            if (result.box) {
                Quagga.ImageDebug.drawPath(result.box, {x: 0, y: 1}, drawingCtx, {color: "#00F", lineWidth: 2});
            }
            
            if (result.codeResult && result.codeResult.code) {
                Quagga.ImageDebug.drawPath(result.line, {x: 'x', y: 'y'}, drawingCtx, {color: 'red', lineWidth: 3});
            }
        }
    });
    
    Quagga.onDetected(function(result) {
    	if (result.codeResult.code){
    		onScan.simulate(document, result.codeResult.code);
    		window.scrollTo(0, 0);
    		$('#{$button->getId()}_scanner').modal('hide');
    	}
    });
});

JS;
        }
        
        // Add ScannerDetector in any case, as the camera scanner
        // will simply trigger it (the camera behaves as a keyboard
        // scanner)
        return $result . $this->getOnScanJsScanner()->buildJsScannerInit($facade);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\BarcodeScanner\Interfaces\JsScannerWrapperInterface::getIncludes()
     */
    public function getIncludes(FacadeInterface $facade) : array
    {
        return [
            $this->buildUrlIncludePath('bower-asset/quagga/dist/quagga.min.js', $facade)
        ];
    }
    
    /**
     * 
     * @return OnScanJsScanner
     */
    protected function getOnScanJsScanner() : OnScanJsScanner
    {
        if ($this->onScanJsScanner === null) {
            $this->onScanJsScanner = new OnScanJsScanner($this->getScanAction());
        }
        return $this->onScanJsScanner;
    }
    
    /**
     *
     * @return iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO add other properties!
        return new UxonObject([
            'type' => 'camera'
        ]);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\BarcodeScanner\Actions\Scanners\AbstractJsScanner::getType()
     */
    public function getType() : string
    {
        return AbstractScanAction::SCANNER_TYPE_QUAGGA;
    }
}