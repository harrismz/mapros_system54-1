<template>
  <div>
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    
                    <div :class="{'panel-body': true, 'panel-body-color': config.isRework }">
                        <form class="form-horizontal" role="form" @submit.prevent='onSubmit' >
                            <div class="form-group text-center">
                                <h3><strong>{{ form_title }}</strong></h3>
                            </div>

                            <div class="form-group">
                                <label for="nik" class="col-md-4 control-label">NIK</label>
                                <div class="col-md-6">
                                    <div class="button-group">
                                        <input placeholder="Scan NIK disini" id="nik" type="search" maxlength="10" class="form-control" name="nik" v-model='form.nik' required autofocus @keyup='nikOnKeyup' @keyup.13.prevent='boardOnFocus'>
                                        <!-- <span id="searchclear" class="fa fa-window-close"></span> -->
                                    </div>
                                </div>
                            </div>

                            <div v-if="config.isTouchUp" class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <!-- <input type="checkbox" id="checkbox" v-model="form.is_solder"> -->
                                    <toggle-button v-model="showLocationForm" :color="'#2ab27b'" :sync='true' :labels="true"/>
                                    <label for="checkbox"> Show Ref No </label>
                                </div>
                            </div>
                            <!-- &&  -->
                            <location 
                                v-if="config.isTouchUp && showLocation" 
                                ref="location"
                                @locationAdded="locationAdded"
                                @locationRemove="locationRemove"
                                :config="config"
                            />

                            <div v-if='config.showCritical'>
                                <div class="form-group" :key="index" v-for="(critical, index ) in form.critical_parts" >
                                    <label for="critical_parts" class="col-md-4 control-label">Critical Part</label>
                                    <div class="col-md-6" >
                                        <div class="input-group">
                                            <input :id="'critical_parts_' + index" :ref="'critical_parts_' + index" placeholder="Scan Critical Part disini" class="form-control" name="critical_parts" v-model='form.critical_parts[index]' required autofocus>        

                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-success" @click.prevent='addCritical' ><span class="fa fa-plus"></span> </button>
                                                <button type="button" class="btn btn-danger" @click.prevent='minusCritical(index)' ><span class="fa fa-minus"></span> </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>                            
                            </div>

                            <div class="form-group">
                                <label class="col-md-4 control-label">{{ label.id }}</label>

                                <div class="col-md-6">
                                    <input :placeholder="'Scan '+ label.id" id="board_id" ref='board_id' v-model="form.board_id" type="board_id" @input='filterBoard' class="form-control" name="board_id"  required>
                                </div>
                            </div>

                            <div class="form-group" v-if="config.isLDPE" >
                                <label class="col-md-4 control-label">{{ label.pet }}</label>

                                <div class="col-md-6">
                                    <input  id="pet" :placeholder="label.pet" ref='pet' v-model="form.pet" type="pet" class="form-control" name="pet"  required @keyup='petLdpeOnKeyup' @keyup.13.prevent='boardOnFocus'>
                                </div>
                            </div>
                            <div class="form-group" v-if="config.isLDPE" >
                                <label class="col-md-4 control-label">{{ label.ldpe }}</label>

                                <div class="col-md-6">
                                    <input  id="ldpe" :placeholder="label.ldpe" ref='ldpe' v-model="form.ldpe" type="ldpe" class="form-control" name="ldpe"  required @keyup='petLdpeOnKeyup' @keyup.13.prevent='boardOnFocus'>
                                </div>
                            </div>

                            <div class="form-group" v-if='config.isManualInstruction'>
                                <label class="col-md-offset-4 col-md-6"> Show Instruction QR textfield : <toggle-button v-model="showManualInstruction" :color="'#2ab27b'" :labels="true" /></label>
                            </div>

                            <div v-if="(config.isManualInstruction && includeIn) || showManualInstruction " >
                                <div class="form-group">
                                    <div class="col-md-offset-4 col-md-6">
                                        <button type="button" class="btn btn-success" @click.prevent="manualInstructionQty++" >Add</button>
                                    </div>
                                </div>

                                <div class="form-group" :key="i" v-for="i in manualInstructionQty">
                                    <label class="col-md-4 control-label">Manual Intruction {{i}}</label>
                                    <!-- jumlah input based on jumlah manual instruction qty -->
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <input :placeholder="'Scan Manual Intruction '+i" ref='manual_content' v-model="form.manual_content[i-1]" class="form-control" name="manual_content"  required>
                                            <span class="input-group-btn">
                                                 <button type="button" class="btn btn-danger" @click.prevent="manualInstructionQtyDeleteOnClick(i-1)" >x</button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" v-if='config.isScanCarton'>
                                <label class="col-md-offset-4 col-md-6"> Show Carton textfield : <toggle-button v-model="showCarton" :color="'#2ab27b'" :labels="true" /></label>
                            </div>

                            <div class="form-group" v-if="(config.isScanCarton && includeIn) || showCarton " >
                                <label class="col-md-4 control-label">Carton</label>
                                <div class="col-md-6">
                                    <input placeholder="Scan Carton" ref='carton' v-model="form.carton" class="form-control" name="carton"  required>
                                </div>
                            </div>

                            <div class="form-group" v-if='isJoin'>
                                <label class="col-md-offset-4 col-md-6"> Proses Join Active : <toggle-button v-model="isJoin" :color="'#2ab27b'" :labels="true" /></label>

                                <label class="col-md-offset-4 col-md-6"> Join Ke {{ computedJumlahJoin }} dari {{config.jumlahJoin}}</label>
                            </div>

                            <div class="form-group" v-if="config.isAutolinezero" >
                                <label class="col-md-4 control-label">{{ label.serialAutolinezero }}</label>

                                <div class="col-md-6">
                                    <input  id="serialAutolinezero" :placeholder="label.serialAutolinezero" ref='serialAutolinezero' v-model="serialAutolinezero" type="serialAutolinezero" class="form-control" name="serialAutolinezero"  required>
                                </div>
                            </div>

                            <div class="form-group" v-if="config.isPwbId" >
                                <label class="col-md-4 control-label">{{ label.pwbId }}</label>

                                <div class="col-md-6">
                                    <input  id="pwbId" :placeholder="label.pwbId" ref='pwbId' v-model="form.pwbId" type="pwbId" class="form-control" name="pwbId"  required>
                                </div>
                            </div>
                            

                            <div class="form-group" v-if="config.isQrPanel" >
                                <label class="col-md-4 control-label">{{ label.qrPanel }}</label>

                                <div class="col-md-6">
                                    <input  id="qrPanel" :placeholder="label.qrPanel" ref='qrPanel' v-model="form.qrPanel" type="qrPanel" class="form-control" name="qrPanel"  required>
                                </div>
                            </div>

                            <div class="form-group" v-if="config.isSirius" >
                                <label class="col-md-4 control-label">{{ label.sirius }}</label>

                                <div class="col-md-6">
                                    <input  id="sirius" :placeholder="label.sirius" ref='sirius' v-model="form.sirius" type="sirius" class="form-control" name="sirius"  required>
                                </div>
                            </div>

                            <div v-if="config.showSolder" class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <!-- <input type="checkbox" id="checkbox" v-model="form.is_solder"> -->
                                    <toggle-button v-model="form.is_solder" :color="'#2ab27b'" :sync='true' :labels="true"/>
                                    <label for="checkbox"> SOLDER </label>
                                </div>
                            </div>  

                            <div v-if="config.showNgoption" class="form-group">
                                <div :class="{'col-md-2':isNG, 'col-md-6':!isNG, 'col-md-offset-4': true }">
                                    <toggle-button v-model="isNG" :color="'#960a0a'" :sync='true' :labels="true"/>
                                    <label for="checkbox"> NG </label>
                                </div>
                                <div class="col-md-4" v-if='isNG'>
                                    <v-select
                                        placeholder='ketik untuk kode symptom / kategori symptom'
                                        multiple 
                                        v-model='form.symptom'
                                        :maxHeight='"200px"' 
                                        label="category" 
                                        :options="options"
                                        index="code"
                                        @search="onSearch" >
                                        <template slot="option" slot-scope="option">
                                            {{ option.code }} - {{ option.category }}
                                        </template>
                                    </v-select>
                                    <!-- <input type="text" class="form-control" name=""> -->
                                </div>
                            </div>

                            <div class="form-group" v-if="config.isScanSN" >
                                <label class="col-md-4 control-label">Show Serial Number</label>                                
                                <div class="col-md-6">
                                    <toggle-button v-model="showSerialNumberField" :color="'#2ab27b'" :sync='true' :labels="true"/>
                                </div>
                            </div>

                            <div class="form-group" v-if="(config.isScanSN && includeIn) || showSerialNumberField " >
                                <label class="col-md-4 control-label">Serial Number</label>
                                <div class="col-md-6">
                                    <input 
                                        placeholder="Scan Serial Number" 
                                        ref='serial_number' 
                                        v-model="form.serial_number" 
                                        class="form-control" 
                                        name="serial_number"  
                                        required
                                    >
                                </div>
                            </div>

                            <div class="form-group" v-if='isLoading' >
                                <div class="col-md-3 col-md-offset-4">
                                    <loading />
                                </div>
                            </div>

                            <!-- <location></location> -->

                            <div class="form-group">
                                <div class="col-md-12 col-xs-12">
                                    <div class="well no-bottom-margin" :style="styles" >
                                        <div class="custom-color">
                                            <div class="row">
                                               <div class="col-md-6 col-sm-6 col-xs-7">
                                                LINE : <strong> {{ info.line }} </strong>
                                               </div>
                                               <div class="col-md-6 col-sm-6 col-xs-5 text-right pull-right float-right">
                                                   TYPE : {{ info.type }}
                                               </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 col-sm-6 col-xs-7">
                                                    PROCESS: <strong> {{info.process}} </strong>
                                                </div>
                                                <div class="col-md-6 col-sm-6 col-xs-5 text-right pull-right float-right">
                                                   STEP ID : {{ info.lineprocess_id }}
                                               </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 col-sm-6 col-xs-12">
                                                    MODEL : <strong> {{form.modelname}} </strong>
                                                </div>
                                            </div>
                                            <hr class="black">
                                        </div>
                                        <div class="text-center">
                                            Information Status: <br>
                                            <!-- <div :class='{"text-danger": hasError, "text-success": !hasError }'> -->
                                                <strong> {{error}} </strong>
                                            <!-- </div> -->
                                            <!-- :class='{"text-danger": hasError, "text-success": !hasError }' -->
                                            <H2  ><strong>{{ (hasError) ? 'NG':'OK' }}</strong></H2>

                                            <a :style='styles' @click.prevent="showDetailError" >See Details >></a>
                                        </div>
                                    </div>
                                </div>
                            </div>                            
                            
                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <button 
                                        v-if='!config.isShowDeleteButton' 
                                        type="submit" 
                                        :class="{btn:true, 'btn-success': !isNG, 'btn-danger':isNG }" >
                                        Submit <i class="fa fa-check float-right"></i>
                                    </button>

                                    <button v-if='config.isShowDeleteButton' @click.prevent='deleteOnClick'  type="submit" class="btn btn-danger">
                                        Delete <i class="fa fa-trash float-right"></i>
                                    </button>

                                    <button v-if="(config.isSendAjax || config.isGenerateFile) && ( includeIn || showResendBtn ) " @click.prevent='resendData' class="btn btn-warning">Resend Data <i class="fa fa-arrow-right"></i> </button>

                                    <!-- <button class="btn btn-warning form-control" @click.prevent="boardOnFocus">Test</button> -->
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <modal 
        v-if="showModal" 
        @toggleModal='toggleModal'
       v-bind:message="modal.message"
       v-bind:header="modal.header"  
    ></modal>
    <confirm 
        v-if='showConfirm'
        @toggleModal='toggleModal'
        @toggleConfirm='toggleConfirm'
        @changeConfig='changeConfig'
        v-bind:config_modelname='form.modelname'
        v-bind:server_modelname='server.modelname'
    ></confirm>
    <join v-if='showJoin' 
        :form='form'
        :errors='errors'
        @toggleJoin='toggleJoin'
        @toggleModal='toggleModal'
    ></join>
  </div>
</template>

<script>
    const axios = require('axios');
    import modal from './Modal';
    import loading from './Loading';
    import confirm from './Confirm';
    import alert from './Alert';
    import join from './Join';
    import ToggleButton from 'vue-js-toggle-button/src/Button';
    import vSelect from 'vue-select';
    import _ from 'lodash';
    import Location from './location/Location.vue';
    
    export default {
        data: () => {
            return {
                form : {
                    ip: '',
                    board_id: '',
                    nik: '',
                    modelname:'',
                    is_solder:false,
                    judge : 'OK', //default nya OK
                    symptom: [], //default value for symptom is empty array;
                    critical_parts:[], //default value for critical_parts empty array, but when it's there, it's buggy. when it's not, it's useless
                    locations:[],
                    isRework : false, //default value
                    manual_content : [], //null, //default value
                    carton : null,
                    serial_number: null,
                    fifoMode: false, //ini refer ke config;
                    pwbId: '', // ini tambahan scan panel4 untuk model DT
                    qrPanel: '', // ini tambahan scan inspect7 untuk model DT
                    sirius: '', // ini tambahan scan inspect7 untuk model DT
                    pet: '', // ini tambahan scan inspect7 untuk model DT
                    ldpe: '' // ini tambahan scan inspect7 untuk model DT
                },

                isNG : false,
                options : [], 
                isJoin : false,
                showManualInstruction: false,
                manualInstructionQty:1, //ini nanti diubah based on modelname

                showCarton:false,
                showSerialNumberField:false,

                oldForm : {
                    ip: '',
                    board_id: '',
                    nik: '',
                    modelname:'',
                    is_solder:false,
                },

                server:{
                    modelname:''
                },

                error: '',
                hasError: false,

                responseText: '',

                detailError: '',

                showAlert : false,
                showJoin  : false,

                info : {
                    line    : '',
                    proces  : '',
                    type    : '',
                    lineprocess_id : '',
                },

                state : 'in',

                isLoading:false,
                showModal: false,
                showConfirm:false,

                label : {
                    id : 'Board ID',
                    serialAutolinezero : 'Serial Set',
                    qrPanel : 'QR Panel', // ini tambahan scan inspect7 untuk model DT
                    sirius : 'Sirius Code ( SXM )', // ini tambahan scan inspect7 untuk model DT
                    pwbId : 'PWB ID',
                    pet : 'PET Label', // ini tambahan scan packing1 untuk model DT
                    ldpe : 'LDPE Label', // ini tambahan scan packing1 untuk model DT
                },

                jumlahJoin: 0, //current jumlah join

                styles : {}, //dipakai di warna well

                // it's basically will be override by getConfig method
                config : {
                    modelname: '',
                    ip       : '',
                    
                    // showCritical : false, //it cannot have default value, otherwise, wathc will not called; let it be;
                    
                    showSolder: true,
                    isGenerateFile : false,
                    generatedFileName : 'something.txt',
                    generateFileData:'DUMMY',
                    isSendAjax : false,
                    isShowDeleteButton : false,
                    isAutolinezero : false,
                    uri : '',
                    showNgoption : false,
                    toggleNgMode : '',
                    jumlahJoin:1, //default value of jumlah join
                    esdUri: '',
                    checkEsd:'',
                    isRework : false, // i'll be overriden by config
                    isManualInstruction : false,
                    isLDPE : false,
                    isScanCarton:false, //default value
                },

                serialAutolinezero:'', //default value of serial
                
                responseData: {
                    success: true,
                    message: '',
                },

                oldResponseData: {
                    success: true,
                    message: '',
                },

                downloadContent : null,

                modal: {
                    header: 'Header',
                    message: 'message'
                },

                showLocationForm : false,
            }
        },

        watch:{
            clonedForm: function(newVal, oldVal){
              // console.log({newVal, oldVal})
              this.oldForm = oldVal;
            }, 

            clonedResponseData(newVal, oldVal ){
                this.oldResponseData = oldVal;
            },

            isJoin(newVal, oldVal){
                this.isJoinOnChange();
            },

            isNG(isNG, oldVal){
                var judge;
                if(isNG){
                    judge = 'NG';
                    this.form.symptom = [{code:99, category: 'OTHER'}]; //DEFAULT VALUE
                    this.fetchSymptomCode();
                }else{
                    judge = 'OK';
                    delete this.form.symptom;
                }
                this.form.judge = judge;
            },

            'config.isRework': function(newVal, oldVal ){
                this.form.isRework = newVal;
            },

            'config.fifoMode': function(newVal, oldVal) {
                this.form.fifoMode = newVal;
            },

            computedShowCritical(newVal, oldVal){
                if (newVal == true) {
                    this.form.critical_parts = [''];
                }else{
                    delete this.form.critical_parts;
                }
                // console.log('showCritical watch called', this.form, newVal )
            },            
        },

        computed:{
            clonedForm: function(){
               return JSON.parse(JSON.stringify( this.form ))
            },

            clonedResponseData(){
                return JSON.parse(JSON.stringify( this.responseData ))
            },

            computedJumlahJoin(){
                return this.jumlahJoin + 1;
            },

            computedShowCritical(){
                // console.log('showCritical computed called', this.config.showCritical )
                return this.config.showCritical 
            },

            showLocation(){
                return  (this.responseData.message.includes('IN /') || this.showLocationForm);
            },

            includeIn(){
                return this.responseData.message.includes('IN /');
            },

            showResendBtn(){
                if(this.responseData.errors){
                    if( this.responseData.errors.showResend ){
                        return true;
                    }
                }
                
                return false;
            },

            guid(){
                var data;
                var node;
                if(typeof this.responseData.node == "undefined"){
                    // error, terjadi ketika awalnya belum in, langsung mau resend data
                    node = this.responseData.errors.node;
                }else{
                    node = this.responseData.node;
                }
                if(node.guid_master != null ){
                    data = node.guid_master;
                }else if (node.guid_ticket != null ){
                    data = node.guid_ticket;
                }else {
                    data = 'NA';
                }

                return data;
            },

            form_title(){
                if(this.config.isRework){
                    return "PLEASE SCAN DATA REWORK";
                }else{
                    return "PLEASE SCAN DATA";
                }
            },
        },

        mounted(){
            // setting event on root 
            let self = this;
            this.$root.$once('GeneratedFile', ( dummy='MAMST', enter='\r\n', serial = '#NA') => {
                // your code goes here
                // let dummy = 'dummy_id_goes here';
                // let serial = 'serial goes here ';
                // let enter = '\n';
                let data = dummy + enter + serial ;
                let filename  = 'example.txt';
                self.download(data, filename );
            });
            // console.log('mounted')
            this.getConfig();
            this.getInfo();
            this.fetchInstructionManualQty();
        },

        components: {
            modal, loading, confirm, alert, join, ToggleButton, vSelect, Location
        },

        methods : {
            onSubmit(){
                let data = this.form;
                // console.log('pertama', {data });
                if( this.toggleMode() == 'break' ){
                    return;
                };

                /* if(!this.checkSerialSetOk()){
                    return;
                } */

                let self = this;
                this.toggleLoading();
                axios.post('api/main', data )
                    .then((response) => {
                        self.toggleLoading()
                        self.handleSucces(response)
                        if (this.form.locations.length > 0 ) {
                            self.clearLocationData();
                        }
                    })
                    .catch( (error) => {
                        self.toggleLoading();
                        if (this.form.locations.length > 0 ) {
                            self.clearLocationData();
                        }
                        if(error == undefined ){
                            this.handleError('TOLONG RELOAD APLIKASI DENGAN F5!', {} )
                            return;
                        }

                        if(error.response == undefined ){
                            this.handleError('TOLONG RELOAD APLIKASI DENGAN F5!', {errors : error })
                            return;
                        }

                        if(error.response.data == undefined){
                            this.handleError('TOLONG RELOAD APLIKASI DENGAN F5!', { errors : error.response })
                            return;
                        }

                        let data = error.response.data;
                        this.responseData = error.response.data;
                        console.log(data, 'on catch')
                        let message = data.message;
                        
                        if(message == 'view'){
                            let pesan = this.form.board_id + ' IN / OK';
                            this.hasError = false;
                            this.error = pesan;
                            this.detailError = pesan;
                            this.changesColor('yellow');
                            this.returnJoin(data.errors);
                            return;
                        }

                        if(message == 'confirmation-view'){

                            this.returnViewConfirmation(data);
                            return;
                        }

                        this.handleError(message, data );
                    })
            },

            checkSerialSetOk() {
                // let serialEmpty = (this.serialAutolinezero == '');
                // kalau sekarang state nya IN dan 
                if( this.includeIn ) {
                    if(this.serialAutolinezero != ""){
                        // cek oldForm.board_id and form.board_id
                        if(this.downloadContent != null) {
                            let boardContent = this.downloadContent.split(this.config.delimiter);
                            if( boardContent[0] != this.form.board_id ) {
                                this.handleError( boardContent[0] +" BELUM DI SCAN OUT. TOLONG SCAN OUT DULU.")
                                return false;
                            }
                        }
                    }
                }

                return true;
            },

            nikOnKeyup(e){
                if(this.config.checkEsd && ( this.form.nik.length >= 5 ) ){
                   this.checkEsd(this)
                }
            },

            petLdpeOnKeyup(e){
                if(this.config.isLDPE ){
                //    this.checkLdpe(this)
                }
            },
            checkLdpe : _.debounce(( self ) => {
                // const url = self.config.esdUri;
                // const ldpe = self.form.ldpe;
                // const pet = self.form.pet;

                // if(pet.length > 0){
                //     self.form.ldpe = '';
                //     self.form.pet = '';
                //     // self.$refs.pet.focus();

                //     self.toggleModal('WARNING', 'PET ERROR' );
                // }


            //   axios.get(url, {
            //     params : {
            //         nik : nik
            //     }
            //   })
            //   .then((res) => {
            //     console.log('success', res)  
            //   }).catch((error) => {
            //     let data = error.response.data;
            //     console.log(data)
            //     // self.clearForm();
            //     self.form.nik = '';

            //     self.toggleModal('WARNING', data.message );

            //   });

            }, 350),

            getLocationData(){
                let location =  this.$refs.location;
                if (location) {
                    // this is data from location component
                    return location.form
                }else{
                    return []; //empty array
                }
            },

            clearLocationData(){
                let location =  this.$refs.location;
                if (location) {
                    location.clearAll();
                }

                this.form.locations = [];
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
                let data = error.response.data;
                console.log(data)
                // self.clearForm();
                self.form.nik = '';

                self.toggleModal('WARNING', data.message );

              });

            }, 350),

            addCritical(){
                // console.log('addCritical triggered')
                this.form.critical_parts.push('');
            },

            minusCritical(index){
                // console.log('minusCritical triggered')
                if (this.form.critical_parts.length != 1) {
                    this.form.critical_parts.splice(index, 1);
                }else{
                    this.form.critical_parts = ['']; //make textfield empty
                    console.log( this.$refs )
                    var el = this.$refs['critical_parts_'+index][0];
                    if (el) {
                        el.focus()
                    }
                }
            },

            changesCriticalCounter($i){
                this.config.criticalCounter = this.config.criticalCounter + $i;
            },

            toggleMode(){
                if(this.config.showSolder){
                    if(this.form.board_id == this.config.toggleSolderMode ){
                        this.form.is_solder = !this.form.is_solder;
                        this.clearForm();
                        return 'break';
                    }
                }

                if(this.config.showNgoption){
                    if(this.form.board_id == this.config.toggleNgMode ){
                        this.isNG = !this.isNG;
                        this.clearForm();
                        return 'break';
                    }
                }    
            },

            filterBoard(evt){
                let board_id = this.form.board_id;
                if (board_id.includes('&')) {
                    this.form.board_id = '';
                    let el = document.querySelector( ':focus' );
                    if( el ) el.blur();
                    this.toggleModal('Information', 'HASIL SCAN MENGANDUNG "&" TOLONG ULANGI!')
										return;
								}
								this.form.board_id = board_id.toUpperCase();
            },

            onSearch(search, loading ){
                loading(true);
                this.search(loading, search, this );
            },

            search: _.debounce((loading, search, vm) => {
              const url = 'api/symptoms/all';

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
                
                console.log(data)

                loading(false);
              });

            }, 350),

            fetchSymptomCode(){
                //fetch data for selectbox;
                const url = 'api/symptoms/all';

              axios.get(url)
              .then(res => {
                // res.json().then(json => (vm.options = json.items));
                let response = res.data;
                let data = response.data;
                this.options = data;
              }).catch(res => {
                console.log(res , 'error fetch data!')
              });
            },

            changesColor(color){
                let yellow = {
                    backgroundColor : '#e5ff12',
                    'border-color'    : '#888080',
                }

                let green = {
                    backgroundColor : '#11b90e',
                    color           : 'white',
                    'border-color'    : '##819289'
                }

                let red  = {
                    color : '#d2c6c6',
                    backgroundColor : '#8e0d0d',
                    'border-color' : '#888080'
                }

                if(color == 'red'){
                    this.styles = red;
                }else if (color == 'yellow'){
                    this.styles = yellow;
                }else{
                    this.styles = green;
                }
            },

            download(data, filename, type) {
                var file = new Blob([data], {type: type});
                console.log(data ,'download');
                if (window.navigator.msSaveOrOpenBlob) // IE10+
                    window.navigator.msSaveOrOpenBlob(file, filename);
                else { // Others
                    var a = document.createElement("a"),
                            url = URL.createObjectURL(file);
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function() {
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);  
                    }, 0); 
                }
            },

            boardOnFocus(){

                if(this.config.isGenerateFile && this.config.isAutolinezero && this.serialAutolinezero == '' ){
                    let serialAutolinezero = document.getElementById('serialAutolinezero');
                    serialAutolinezero.focus();
                }
                if(this.config.isGenerateFile && this.config.isQrPanel && this.form.qrPanel == '' ){
                    let qrPanel = document.getElementById('qrPanel');
                    qrPanel.focus();
                }
                if(this.config.isGenerateFile && this.config.isPwbId && this.form.pwbId == '' ){
                    let pwbId = document.getElementById('pwbId');
                    pwbId.focus();
                }
                if(this.config.isGenerateFile && this.config.isSirius && this.form.sirius == '' ){
                    let sirius = document.getElementById('sirius');
                    sirius.focus();
                }

                if(this.config.isLDPE && this.form.pet == '' ){
                    let pet = document.getElementById('pet');
                    pet.focus();
                }
                if(this.config.isLDPE && this.form.ldpe == '' ){
                    let ldpe = document.getElementById('ldpe');
                    ldpe.focus();
                }

                if(this.form.board_id == ''){
                    // let boardInput = document.getElementById('board_id');
                    let boardInput = this.$refs.board_id; //changes to using ref
                    if(boardInput){
                        boardInput.focus();
                    }
                }

                if(this.form.nik == ''){
                    let nikInput = document.getElementById('nik');
                    if(nikInput){
                        nikInput.focus();
                    }
                }
                // console.log('board on focus triggered')
            },

            handleError(message, detailError = '' ){
                this.error = message;
                this.detailError = detailError;
                this.hasError = true;
                this.changesColor('red');
                this.playNG(); //play sound NG
                this.form.board_id='';
                this.clearForm(); //clear form;
                if(this.config.isScanSN) {
                    this.form.serial_number = ''; //kita harus pikirkan apakah kita hapus ini atau engga.
                    this.showSerialNumberField = true;
                    this.boardOnFocus();
                }

                if(this.config.isManualInstruction) {
                    if(this.error.includes('MANUAL INSTRUCTION')){
                        this.showManualInstruction = true;
                    }
                }
            },

            handleSucces(response){
                // set error to default value to show alert-success in alert
                console.log('handleSucces', response )
                let message = response.data.message;
                this.responseData = response.data;
                this.hasError = false;
                this.error = message;
                this.detailError = this.responseData; //message;

                if (response.data.node.status == 'IN') { 
                    if( this.config.isSendAjax ){
                        this.sendAjax(this.responseData)    
                    }

                    if(this.config.isGenerateFile){
                        this.generateFile();
                    }

                    // this code below is work because when view is return, it is throw error with message view
                    // tambah counter jumlahJoin
                    if(this.isJoin){
                        // this.jumlahJoin = response.node.
                        this.jumlahJoin++;
                        if(this.jumlahJoin >= this.config.jumlahJoin){
                            console.log('jumlahJoin tercapai')
                            this.isJoin = false; //tutup join otomatis
                            this.jumlahJoin=0; //back to default
                        }
                    }
                }

                if(message.includes('IN')){
                    this.changesColor('yellow')
                }else {
                    this.changesColor('green')
                }

                if(message.includes('NG')){
                    this.changesColor('red')
                }

                if(this.config.isManualInstruction) {
                    this.showManualInstruction = false;
                }
                // this.toggleAlert('Success', message );
                // this.showAlert = true;
                this.playOk(); //play sound ok
                this.clearForm();
                this.boardOnFocus();
                this.isNG = false; //turn off toggle mode
                // set focus
            },

            clearForm(){
                this.form.board_id = '';
                if(this.config.isGenerateFile || this.config.isAutolinezero ) {
                    // kalau IN jangan dulu dihapus;
                    if(!this.responseData.message.includes('IN')) this.serialAutolinezero = '';
                }
                if(this.config.isQrPanel ) {
                    // kalau IN jangan dulu dihapus;
                    if(!this.responseData.message.includes('IN')) this.form.qrPanel = '';
                }
                if(this.config.isSirius ) {
                    // kalau IN jangan dulu dihapus;
                    if(!this.responseData.message.includes('IN')) this.form.sirius = '';
                }
                if(this.config.isLDPE ) {
                    // kalau IN jangan dulu dihapus;
                    if(!this.responseData.message.includes('IN')) this.form.pet = '';
                    if(!this.responseData.message.includes('IN')) this.form.ldpe = '';
                }
                /*kalau config showNgOption itu false, baru jalankan*/
                if (!this.config.showNgoption) { this.isNG = false; }
                if(this.config.isManualInstruction){this.form.manual_content = [] }
                if(this.config.isScanCarton ){this.form.carton = null }
                if(this.config.isScanSN ) {
                    this.showSerialNumberField = false; //tutup serial no field
                    this.form.serial_number = null
                }
                
            },

            generateFile(resend = false){
                if ( (typeof this.serialAutolinezero == 'undefined') || this.serialAutolinezero == '' ) {
                    this.serialAutolinezero = 'NA';
                }
                if ( (typeof this.form.pwbId == 'undefined') || this.form.pwbId == '' ) {
                    this.form.pwbId = 'NA';
                }
                if ( (typeof this.form.qrPanel == 'undefined') || this.form.qrPanel == '' ) {
                    this.form.qrPanel = 'NA';
                }
                if ( (typeof this.form.sirius == 'undefined') || this.form.sirius == '' ) {
                    this.form.sirius = 'NA';
                }

                var data = '';
                if(this.config.generateFileData == 'GUID'){
                    // kalau ini, resend atau bukan, sama aja
                    data = this.guid; //from computed value
                }else{
                    if ( this.form.board_id != '' ) {
                        /*pertama kali jalan*/
                        data = this.form.board_id;
                    }else{
                        /*pas mau resend data*/
                        data = this.oldForm.board_id;
                    }    
                }

                var enter = this.config.delimiter; //'';//'\r\n';
                this.downloadContent = data;
                if(this.serialAutolinezero != ''){
                    this.downloadContent += enter + this.serialAutolinezero;
                }
                if(this.form.PwbId != 'NA'){
                    this.downloadContent += enter + this.form.PwbId;
                }
                if(this.form.qrPanel != 'NA'){
                    this.downloadContent += enter + this.form.qrPanel;
                }
                if(this.form.sirius != 'NA'){
                    this.downloadContent += enter + this.form.sirius;
                }
                //  + enter + this.form.qrPanel + enter + this.form.sirius ;

                let filename = this.config.generatedFileName;
                this.download( this.downloadContent, filename );

                return data; //dummy atau guid untuk kepentingan visualisasi
            },

            deleteOnClick(){
                let data = this.form;
                console.log(data)
                // return;
                let self = this;
                this.toggleLoading();

                axios.delete('api/main', {data : data } )
                    .then((response) => {
                        self.toggleLoading()
                        self.handleSucces(response)
                        console.log(response)
                    })
                    .catch( (error) => {
                        let data = error.response.data;
                        console.log(data)
                        let message = data.message;
                        self.toggleLoading()
                        
                        this.handleError(message, data );
                    })
            },

            returnViewConfirmation(error){
                this.server.modelname = error.errors['server-modelname'][0]
                console.log('view-confirmation', error.errors['server-modelname'][0] )
                this.showConfirm = !this.showConfirm;
            },

            showDetailError(){
                // show modal containing the error 
                console.log(this.detailError )
                let errors;
                if(this.detailError.errors){
                    errors = this.detailError.errors
                }else{
                    errors = this.detailError
                }
                this.toggleModal('Information', errors )
            },

            returnJoin(errors){
                /*this.errors = errors
                this.showJoin = true;*/
                this.isJoin = true;
                this.form.guid = errors['guid'][0];
                // update sisa join times on first scan parents
                this.config.jumlahJoin = errors['join_times_left'][0];
                this.clearForm();
                this.boardOnFocus();
            },


            isJoinOnChange(){
                if( this.isJoin == false ){
                    delete this.form.guid //delete guid property from form;
                    this.initLabel();
                }else{
                    this.label.id = 'BOARD / DUMMY TICKET';
                    this.playJoin(); //play sound join
                }
                this.boardOnFocus();
            },

            toggleModal(header = '', message = ''){
                this.modal.header = header;
                this.modal.message = message;
                this.showModal = !this.showModal
                // this.showConfirm = !this.showConfirm;
                // this.isLoading = !this.isLoading;
                if(this.showModal === false ){
                    // set focus on board id;
                    this.boardOnFocus();
                }else{
                    let el = document.querySelector(':focus');
                    if(el){ el.blur() } //no field focus
                }
            },

            toggleConfirm(){
                this.showConfirm = !this.showConfirm
            },

            toggleLoading(){
                this.isLoading = !this.isLoading;
            },

            toggleAlert(){
                this.showAlert = !this.showAlert;
            },

            toggleJoin(){
                this.boardOnFocus();
                this.showJoin = !this.showJoin
            },

            getConfig(){
              let config = localStorage.getItem('config');
              if(config == null ){
                this.$router.push({
                  path: '/config'
                })
              }
              config = JSON.parse(config);
              this.config = config;
              console.log(config);
              this.form.modelname = config.model;
              this.form.ip = config.ip;
            },

            // triggered by child view
            changeConfig(serverModel){
                this.form.modelname = serverModel;
                let newConfig = this.config;
                newConfig.model = serverModel;

                localStorage.setItem('config', JSON.stringify(newConfig) );
                // changes localstorage
                this.onSubmit();
            },

            initLabel(){
                // console.log(this.info, 'set label method')
                if( this.info.lineprocess != undefined ){
                    if(this.info.lineprocess.column_settings != undefined){
                        let column_settings = this.info.lineprocess.column_settings;
                        for (var i = 0; i < column_settings.length; i++) {
                            if( column_settings[i]['table_name'] == 'masters') {
                                this.label.id = 'DUMMY MASTER';
                                return;
                            }

                            if( (column_settings[i]['table_name'] == 'tickets') ) {
                                this.label.id = 'DUMMY TICKET';
                            }
                        }
                    }
                }
            },

            sendAjax(responseData = null){

                var board_id;
                // != GUID to avoid error on client that doesn't have the config
                if(this.config.sendAjaxFileData != 'GUID'){
                    if ( this.form.board_id != '' ) {
                        /*pertama kali jalan*/
                        board_id = this.form.board_id;
                    }else{
                        /*pas mau resend data*/
                        board_id = this.oldForm.board_id;
                    }
                }else{
                    // guid 
                    board_id = this.guid; //computed value;
                }

                let value = board_id;
                
                axios.get(this.config.uri, {
                    params : {
                        valscan : value
                    }
                }).then( (response) => {
                    console.log('Success', response )
                }).catch((error) => {
                    console.log('error', error )
                })

                return value; // dummy atau guid
            },

            getInfo(){
              let modal = this.modal;
              let self= this;
              let config = localStorage.getItem('config');
              let ipConfig = JSON.parse(config);
            //   console.log({ipConfig})
              let ip = this.form.ip || ipConfig.ip
              let modelname = ipConfig.model;
              axios.get('api/configs', {
                params:{
                  ip: ip,
                  modelname: modelname
                }
              }).then((response) => {
                console.log(response)
                var data = response.data.data;
                self.info = data;
                
                if(typeof response.data.show_serial_number_field != undefined ) {
                    console.log('hai', response.data.show_serial_number_field )
                    self.config.isScanSN = response.data.show_serial_number_field;
                }

                self.config.jumlahJoin = data.lineprocess.join_qty;
                self.initLabel();
              })
              .catch((error)=> {

                modal.header = 'ERROR';
                console.log(error)
                modal.message = error.response.data.message;
                self.showModal = !self.showModal; 

              })
            },

            resendData(){
                var ajaxData = '';
                var generateData = '';

                if( (this.config.isSendAjax) && ( this.responseData != null) ){
                    ajaxData = this.sendAjax(this.responseData)
                }

                if(this.config.isGenerateFile){
                    var resend=true;
                    generateData = this.generateFile(resend);
                }

                var data = '';
                if(ajaxData == generateData){
                    data = ajaxData;
                }else{
                    data = ajaxData + " " + generateData;
                }

                let pesan = "RESENDING DATA "+ data;
                this.hasError = false;
                this.error = pesan;
                this.detailError = pesan;
                this.changesColor('yellow');

            },

            toggleHasError(hasError = ''){
                if(hasError == ''){
                    this.hasError = !this.hasError;
                }else{
                    this.hasError = hasError;
                }
            },

            locationAdded(locations){
                // console.log(locations)
                this.form.locations = locations;
                let data = this.form;
                console.log(data, 'locationAdded')
            },

            locationRemove(index){
                // remove array with key = index sebanyak 1 buah
                // dipanggil dari $emit oleh location vue
                this.form.splice(index, 1);
            },

            playSound (sound) {
              if(sound) {
                var audio = new Audio(sound);
                audio.play();
              }
            },

            playOk(){
                this.playSound('./storage/OK.mp3');
            },

            playNG(){
                this.playSound('./storage/NG.mp3');
            },

            playJoin(){
                this.playSound('./storage/join.mp3');
            },

            fetchInstructionManualQty() {
                let modelname = this.config.model;
                console.log(modelname)
                axios.get('api/get_instruction_manual_qty', {
                    params:{
                        modelname: modelname
                    }
                }).then(res => res.data )
                .then(data => {
                    if(data.success){
                        let jml = data.data;
                        if(jml < 1){
                            this.manualInstructionQty = 1;
                        }else{
                            this.manualInstructionQty = jml;
                        }
                    }
                })
                .catch(error => console.log(error));
            },

            manualInstructionQtyDeleteOnClick(index) {
                this.manualInstructionQty--;
                this.form.manual_content.splice(index, 1);
            }
        }
    }

</script>

<style>
    .custom-color{
        background-image: none!important;
        /*background-color: yellow !important;*/
    }

    .black {
        border-color: #636B6F;
        border-width: 2px;
        margin-top: 5px;
        margin-bottom: 5px;
    }

    .txt-color {
        color: #ffffff;
    }

    .bg-color {
        background-color: #edf108;
    }

    .panel-body-color {
        background-color: gold;
    }

    h1, h2, h3, h4 {
        margin-top: 5px;
        margin-bottom: 5px;
    }

    .no-bottom-margin {
        margin-bottom: auto;
    }

    .no-left-margin {
        margin-left: 0px;
    }
</style>
