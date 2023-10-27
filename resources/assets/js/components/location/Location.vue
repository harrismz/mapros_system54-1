<template>
	<div class="container-fluid">
		<div class="row">
            <div class="container-fluid">
                <div class="panel panel-default">
                	<div class="panel-body">
                		<form class="form-horizontal" role="form">
	                		<div class="form-group">
		          				<label class="col-md-4 control-label"> Ref Number </label>
		          				<div class="col-md-8">
		          					<!-- on enter is handled by watch property -->
			          				<v-select
			          					v-model='model.ref_number'
			          					placeholder="Ketik untuk mencari ref number"
		                                label="ref_no" 
		                                :maxHeight='"200px"'
		                                :options="locations"
		                                ref="ref_number"
		                                required
		                                @search="onSearch"
			          				>
			          					<template slot="option" slot-scope="option">
		                                   {{ option.ref_no }}-{{ option.modelname }}-{{ option.pwbname }}
		                                </template>
			          				</v-select>      	
		          				</div>
	                		</div>

	                		<div class="form-group">
	                			<label class="col-md-4 control-label" for='symptoms'>Symptoms</label>
		                		<div class="col-md-8">
		                			<!-- on enter is handled by watch property -->
			                		<v-select
				                        placeholder='ketik untuk kode symptom / kategori symptom'
				                        multiple 
				                        v-model='model.symptoms'
				                        :maxHeight='"200px"' 
				                        label="category" 
				                        :options="symptoms"
				                        ref="symptoms"
				                        required
				                        @search="onSearchSymptom" >
				                        <template slot="option" slot-scope="option">
				                            {{ option.code }} - {{ option.category }}
				                        </template>
				                    </v-select>
		                		</div>
	                		</div>

	                		<div class="form-group">
	                			<div class="col-md-12 col-sm-12 col-xs-12">
	                				<button class="btn btn-success pull-right" @click="addOnClick">ADD <i class="fa fa-plus float-right"></i> </button>
	                			</div>
	                		</div>

	                		 <b-table :responsive="true" striped hover :fields="fields" :items="stores">
	                		 	<template slot="button" slot-scope="row">
							      <button class="btn btn-danger" @click='btnDeleteOnClick(row)' >Delete <i class="fa fa-trash float-right"></i></button>
							    </template>
	                		 </b-table>
                		 </form>
                	</div>
                </div>
            </div>
        </div>
	</div>

</template>

<script>
	import vSelect from 'vue-select';
	import axios from 'axios';

	export default {
		data(){
			return {
				locations:[], //for checkbox options
				symptoms :[], //for checkbox options
				fields : ['ref_number','symptoms', 'button'],
				model: {
					ref_number: null,
					symptoms: [],
				},
				stores:[],
				form:[],
			}
		},

		props : ['config'],

		computed : {
			newForm(){
				let result = {}
				if (this.model.ref_number !== null ) {
					result.ref_number_id = this.model.ref_number.id
				}

				if (this.model.symptoms.length > 0) {
					result.symptoms_id = [];
					this.model.symptoms.forEach(function(symptom){
						result.symptoms_id.push( symptom.code )
					})
				}

				return result;
			},

			row(){
				let ref_number = null;
				let symptoms = null;

				if (this.model.ref_number !== null ) {
					// console.log(this.model.ref_number, 'row')
					ref_number = this.model.ref_number.ref_no
				}

				if (this.model.symptoms.length > 0) {
					let arraySymptomsName = this.model.symptoms.map(function(symptom){
						return symptom['category']
					});

					symptoms = arraySymptomsName.toString();
				}

				return {
					ref_number : ref_number,
					symptoms : symptoms
				}
			}
		},

		components: {
			vSelect, 
		},

		mounted(){
			this.fetchLocations();
			this.fetchSymptomCode();
		},

		watch : {
			/*it's for handle on enter (on scan) listeners*/
			'model.ref_number' : function (newVal, oldVal){
				this.symptomFocus();
			},

			'model.symptoms' : function (newVal, oldVal){
				this.addOnClick();
				this.refNoFocus();
			},
		},

		methods: {
			onSearch(search, loading ){
                loading(true);
                const url = 'api/locations';
                this.search(loading, search, this, url, 'locations' );
            },

            onSearchSymptom(search, loading){
            	loading(true);
            	const url = 'api/symptoms/all';
                this.search(loading, search, this, url, 'symptoms' );
            },

            search: _.debounce((loading, search, vm, url, options ) => {
              axios.get(url, {
                params : {
                    q : search
                }
              })
              .then(res => {
                // res.json().then(json => (vm.options = json.items));
                let response = res.data;
                let data = response.data;
                vm[options] = data;
                loading(false);
              }).catch(res => {
                console.log(res)
              });

            }, 350),

			addOnClick(){
				// console.log('model', this.model)
				// console.log('newForm', this.newForm)
				if (this.verifyForm() == false) {
					// stop the code here
					return;
				}
				this.stores.push( this.row )
				this.addForm(this.newForm)
				this.clear()

				this.fetchLocations(); //re fetch locations
				this.fetchSymptomCode(); // re fetch sysmptoms
			},

			verifyForm(){
				var result = true;
				if (this.row.ref_number == null) {
					// console.log(this.$refs , "aku ref")
					this.refNoFocus();
					result = false;
				}

				if (this.row.symptoms == null) {
					this.symptomFocus();
					result = false;
				}

				return result;
			},

			addForm(newForm){
				this.form.push(newForm)
				this.$emit('locationAdded', this.form )
			},

			btnDeleteOnClick(row ){
				// console.log(row , "btn delete on click")
				let index = row.index;
				this.removeRow(index)
			},

			removeRow(index){
				this.stores.splice(index, 1);
				this.form.splice(index, 1);
				this.$emit('locationRemove', index );
			},

			clear(){
				this.model =  {
					ref_number: null,
					symptoms: [],
				}
			},

			/*called from parent component to clear all data here*/
			clearAll(){
				this.model = {
					ref_number: null,
					symptoms: [],
				}
				
				this.stores = []
				this.form = []
			},
			
			fetchLocations(){
				const url = 'api/locations';
				// console.log(this.config)
				let model_header_id  = this.config.model_header_id;
				let pwb_id = this.config.pwb_id;
				let params = {}
				if(model_header_id != null ){
					params.model_header_id = model_header_id
				}

				if(pwb_id.length > 0 ){
					params.pwb_id = pwb_id;
				}	
				
				axios.get(url, {
					params: params
				})
				.then(res => {
					console.log(res)
					let response = res.data; 
					let data = response.data;
					this.locations = data;
				}).catch(res => {
					console.log(res)
				})
			},

			fetchSymptomCode(){
                //fetch data for selectbox;
              const url = 'api/symptoms/all';
			  let params = {};
			  if(this.config.include_symptom_id.length > 0){
				params.include_symptom_id = this.config.include_symptom_id;
			  }

              axios.get(url , {
				  params: params
			  })
              .then(res => {
                // res.json().then(json => (vm.options = json.items));
                let response = res.data;
                let data = response.data;
                this.symptoms = data;
              }).catch(res => {
                console.log(res , 'error fetch data!')
              });
            },

            symptomFocus(){
            	// console.log(this.$refs)
            	let symptoms = this.$refs.symptoms;
            	// console.log(symptoms, ' hai');
            	if (symptoms) {
            		symptoms.$refs.search.focus()
            	}
            },

            refNoFocus(){
            	let refNo = this.$refs.ref_number;
            	// console.log(refNo)
            	if (refNo) {
            		refNo.$refs.search.focus();
            	}
            }
		}
	}
</script>

<style>
	.container-custom {
		padding: 5px;
	}
</style>