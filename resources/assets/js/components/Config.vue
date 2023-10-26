<template>
	<div class="container">
		<div class="row">        
			<div class="col-md-8 col-md-offset-2">
				<div class="panel panel-default">
					<div class="bg-info panel-heading custom-heading">
						<i class="fa fa-cogs"></i> Config
					</div>
					<div class="panel-body">
						<form class="form-horizontal">
							<div class="form-group">
                                <label for="nik" class="col-md-3 control-label">Current Model</label>
                                <div class="col-md-9">
                                    <input  type="text" v-model='config.model' class="form-control" required autofocus>
                                </div>
                            </div>

							<div class="form-group">
                                <label for="ip_address" class="col-md-3 control-label">ID Scanner / Nama Proses </label>
                                <div class="col-md-9">
                                    <v-select 
                                        v-model='config.ip' 
                                        label="ip_address" 
                                        :maxHeight='"200px"'
                                        :options="options"
                                        index="ip_address"
                                        @search="onSearch" >
                                        <template slot="option" slot-scope="option">
                                            {{ option.ip_address }} - {{ option.name }}
                                        </template>
                                    </v-select>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <!-- <input type="checkbox" id="showSolder" v-model="config.showSolder"> -->
                                    <toggle-button id="showSolder" :sync='true' v-model="config.showSolder"  :color="'#2ab27b'" :labels="true"/>
                                    <label for="showSolder"> show solder options </label>
                                </div>
                            </div>

                            <div class="form-group" v-if="config.showSolder" >
                                <label for="uri" class="col-md-3 control-label">toggle solder mode code</label>
                                <div class="col-md-9">
                                    <input type="input" class=" form-control " placeholder="solder_active" v-model="config.toggleSolderMode" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button id="showCritical" :sync='true' v-model="config.showCritical"  :color="'#2ab27b'" :labels="true"/>
                                    <label for="showCritical"> show critical textfield </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <!-- <input type="checkbox" id="isGenerateFile" v-model="config.isGenerateFile"> -->
                                    <toggle-button id="isGenerateFile" v-model="config.isGenerateFile" :sync='true'  :color="'#2ab27b'" :labels="true"/>
                                    
                                    <label for="isGenerateFile"> Generate file on scan </label>
                                </div>
                            </div>

                            <generate-file-config :config='config' />

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <!-- <input type="checkbox" id="isSendAjax" v-model="config.isSendAjax"> -->
                                    <toggle-button id="isSendAjax" v-model="config.isSendAjax" :sync='true' :color="'#2ab27b'" :labels="true"/>

                                    <label for="isSendAjax"> send data to avn test / avmt </label>
                                </div>
                            </div>

                            <div class="form-group" v-if='config.isSendAjax'>
                                <label for="uri" class="col-md-3 control-label">URI</label>
                                <div class="col-md-9">
                                    <input  type="text" v-model='config.uri' class="form-control" required autofocus>
                                </div>
                            </div>

                            <div class="form-group" v-if='config.isSendAjax'>
                                <label  class="col-md-3 control-label">DATA UNTUK DIKIRIM KE LUAR</label>	
                                <div class=" col-md-6  col-xs-9 col-sm-9">
                                    <b-form-radio-group 
                                        id="radios2" 
                                        v-model="config.sendAjaxFileData" 
                                        :options="radioOptions" 
                                        name="radioSendAjaxFile"
                                    >
                                    </b-form-radio-group>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <!-- <input type="checkbox" v-model="config.isShowDeleteButton"> -->
                                    <toggle-button v-model="config.isShowDeleteButton" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="isShowDeleteButton"> show delete button </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.showNgoption" :sync='true' :color="'#960a0a'" :labels="true"/>
                                    <label for="showNgoption"> show NG button </label>
                                </div>
                            </div>

                            <div class="form-group" v-if="config.showNgoption" >
                                <label for="uri" class="col-md-3 control-label"> NG mode code</label>
                                <div class="col-md-9">
                                    <input type="input" class=" form-control " placeholder="ng_active" v-model="config.toggleNgMode" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.checkEsd" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="checkEsd"> Check ESD </label>
                                </div>
                            </div>

                            <div class="form-group" v-if="config.checkEsd" >
                                <label for="uri" class="col-md-3 control-label"> URL data ESD </label>
                                <div class="col-md-9">
                                    <input type="input" placeholder="http://136.198.117.78/esd/api/esd" class="form-control" v-model="config.esdUri" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.isTouchUp" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="isTouchUp"> is Touch Up Process </label>
                                </div>
                            </div>

                            <div v-if="config.isTouchUp" >
                                <location-config :config="config" />
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.isRework" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="isRework"> REWORK </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.isLdpePet" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="isLdpePet"> Scan LDPE/PET Label ( for DT Model  ) </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.isManualInstruction" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="isManualInstruction"> Scan Manual Instruction </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.isScanCarton" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="isScanCarton"> Scan Inner Carton </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.fifoMode" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="fifoMode"> Activate Fifo Mode </label>
                                </div>
                            </div>

                            <!-- <div class="form-group">
                                <div class=" col-md-6 col-md-offset-3 col-xs-12">
                                    <toggle-button v-model="config.isScanSN" :sync='true' :color="'#2ab27b'" :labels="true"/>
                                    <label for="isScanSN"> Scan Serial Number </label>
                                </div>
                            </div> -->

                            <div class="form-group row">
                                <div class="col-md-9 col-md-offset-3">
                                    <a href="#/" class="btn btn-danger"><i class="fa fa-arrow-circle-left float-right"></i> Cancel</a>
                                    <a href="#/" @click.prevent='toggleForm' class="btn btn-success"><i class="fa fa-save"></i> Save </a>
                                </div>
                            </div>
						</form>
					</div>
				</div>
			</div>
		</div>

        <modal 
            v-if="showForm" 
            @toggleModal='toggleForm'
        >
            <div slot="body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label for="nik" class="col-md-3 control-label">NIK</label>
                        <div class="col-md-9">
                            <input 
                              placeholder="scan your nik"  
                              type="text" 
                              v-model='form.nik' 
                              class="form-control" 
                              required 
                              autofocus
                              @keyup='nikOnKeyup'
                            >
                        </div>
                    </div>

                    

                    <p><strong>{{formErrorMsg}}</strong></p>
                </form>
            </div>
            <div slot="footer">
              <button class="btn btn-danger" @click.prevent="toggleForm">
                Cancel
              </button>

              <button :disabled="nikValidated != false" class="btn btn-success" @click.prevent='save'>
                Save 
              </button>
            </div>
        </modal>
	</div>
</template>

<script>
	import ToggleButton from 'vue-js-toggle-button/src/Button';
    import GenerateFileConfig from './GenerateFileConfig';
    import Alert from './Alert';
    import InputNumber from '@chenfengyuan/vue-number-input';
    import vSelect from 'vue-select';
    import _ from 'lodash';
    import axios from 'axios';
    import locationConfig from './location/Config.vue';
    import modal from './Modal';

	export default {

		data(){
			return {
				config : {
					model:'',
					ip:'',
                    
                    showSolder:false,
                    toggleSolderMode:'toggleSolderMode',

                    showCritical: false,
                    criticals:[{index:0}],

                    // jumlahJoin:1, //will deleted soon due to move to server

					showConfig: false,
                    isGenerateFile : false,
                    generateFileData: 'DUMMY', //default value untuk radio button, bs jd diisi GUID
					isSendAjax : false,
                    sendAjaxFileData: 'DUMMY',
					isShowDeleteButton : false,
					isAutolinezero : false,
					isQrPanel : false,
					isSirius : false,
                    isTouchUp : false,
					generatedFileName : 'something.txt',
					uri : '',
                    isDebug : false,

                    showNgoption: false,
                    toggleNgMode: 'toggleNgMode',

                    checkEsd : true,
                    esdUri : 'http://136.198.117.78/esd/api/esd/index.php',
                    model_header_id : null,
                    pwb_id : [],
                    include_symptom_id:[],

                    isRework : false,

                    isManualInstruction: false,
                    isScanCarton: false,
                    isScanSN : false,
                    fifoMode : false,
				},

                debug : {
                    enterActive : true,
                    content : {
                        dummy:'MAPNL01020001',
                        serial:'#NA',
                        enter : '\r\n',
                    },
                },

                options: [],

                
                radioOptions: ['GUID', 'DUMMY'],

                showForm : false,
                formErrorMsg : null,
                nikValidated: false,
                checkNik: true, //untuk nanti pengaturan server
                form:{
                    nik: null,
                    configvalue: null,
                }

			}
		},

		components : {
			ToggleButton, GenerateFileConfig, Alert, InputNumber, vSelect, locationConfig,
            modal
		},

		mounted(){
			this.getConfig();
            this.fetchIpData();
		},

		methods: {
			save(){
				if (this.config.isSendAjax) {
					if ( typeof this.config.uri == undefined ) {
						alert('you need to fill URI');
						return;
					}
				}
                
                this.form.configvalue = JSON.stringify(this.config);

                for (let item in this.form) {
                    console.log(item)
                    if( this.form[item] == null || this.form[item] == '') {
                        this.formErrorMsg = "Mohon isi semua data !! ( "+ item +" )";
                        return;
                    }
                }

                // kirim form
                axios.post('api/configlog', this.form )
                //   .then(res => res.JSON())
                  .then(res => {
                    this.formErrorMsg = null;
                    localStorage.setItem('config', JSON.stringify(this.config) )
				    this.$router.push('/');
                  })
                  .catch(error => {
                    this.formErrorMsg = error.message || "something went wrong & error message not found. please try again.";
                    console.error(error)
                  })

			},

            onSearch(search, loading ){
                loading(true);
                this.search(loading, search, this );
            },

            search: _.debounce((loading, search, vm) => {
              const url = 'api/scanners/all';

              axios.get(url, {
                params : {
                    q : search
                }
              })
              .then(res => {
                // res.json().then(json => (vm.options = json.items));
                let response = res.data;
                let data = response.data;
                vm.options = data;
                loading(false);
              }).catch(res => {
                console.log(res)
              });

            }, 350),

            fetchIpData(){
                const url = 'api/scanners/all';
                axios.get(url)
                  .then(res => {
                    let response = res.data;
                    let data = response.data;
                    this.options = data;
                  }).catch(res => {
                    console.log(res)
                  });
            },

			getConfig(){
				let currentConfig = localStorage.getItem('config')
				if(currentConfig != null){
					currentConfig = JSON.parse(currentConfig);
					this.config = currentConfig; 
					
				}
            },
            
            toggleForm(){
                this.formErrorMsg = null
                this.showForm = !this.showForm

                if(this.showForm) {
                    // fetch config from server.
                    axios.get('api/getconfig')
                      .then(res=> {
                          console.log(res)
                          if(res == undefined) {
                              return;
                          }

                          if(res.data != undefined) {
                            // force_setting_esd pengaturan yg relatif baru
                            if(res.data.force_setting_esd) {
                                // kalau ga ketemu, di hardcode darisini.
                                this.config.esdUri = res.data.esd_uri || 'http://136.198.117.78/esd/api/esd/index.php';
                                this.config.checkEsd = (res.data.check_esd == "1") || false;
                                // kalau check nik false, gausah verify nik dari ESD
                                this.checkNik = (res.data.check_esd == "1") || false;
                            }

                            if(res.data.esd_uri != undefined) {
                                this.formErrorMsg = 'validating NIK from server to '+ res.data.esd_uri
                            }else {
                                this.formErrorMsg = 'Validating NIK from Client Config : ' + this.config.esdUri
                            }
                          }
                      })
                      .catch(error => {
                          console.error(error);
                          let data = error.response.data;
                          this.formErrorMsg = data
                      })
                }
            },

            checkEsd : _.debounce(( self ) => {
              const url = self.config.esdUri;
              const nik = self.form.nik;

              axios.get(url, {
                params : {
                    nik : nik
                }
              })
              .then((res) => {
                console.log('success', res)  
              }).catch((error) => {
                console.error(error)
                if(error == undefined) {
                    self.formErrorMsg = "error undefined";
                    return;
                }

                if(error.response == undefined) {
                    self.formErrorMsg = "error response undefined";
                    return;
                }

                if(error.response.data == undefined) {
                    self.formErrorMsg = "error response data undefined";
                    return;
                }

                let data = error.response.data || undefined;
                console.log(data)
                // self.clearForm();
                self.form.nik = '';

                self.formErrorMsg = data.message || "TERJADI KESALAH KETIKA CHECK ESD. MOHON COBA LAGI.";

              });

            }, 350),

            nikOnKeyup(e){
                if(this.checkNik){
                    if( (this.form.nik != null) && ( this.form.nik.length >= 5 ) ){
                    this.checkEsd(this)
                    }
                }
            },
		}
	}
</script>