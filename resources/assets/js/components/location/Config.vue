<template>
	<div class="withBorder">
		<div class="form-group">
            <label for="modelname" class="col-md-3 control-label">Model Name</label>
            <div class="col-md-9">
                <v-select 
                    v-model='config.model_header_id' 
                    label="name" 
                    :maxHeight='"200px"'
                    :options="modelnames"
                    index="id"
                    placeholder="Modelname"
                    @search="onSearchModelname" >
                    	<template slot="option" slot-scope="option">
                            {{ option.id }} - {{ option.name }}
                        </template>
                </v-select>
            </div>
        </div>

        <div class="form-group">
            <label for="pwbname" class="col-md-3 control-label">Pwb Name</label>
            <div class="col-md-9">
                <v-select 
                    v-model='config.pwb_id' 
                    label="name" 
                    :maxHeight='"200px"'
                    :options="pwbs"
                    index="id"
                    placeholder="Pwb name"
                    multiple
                    @search="onSearchPwbs" >
                    	<template slot="option" slot-scope="option">
                            {{ option.id }} - {{ option.name }}
                        </template>
                </v-select>
            </div>
        </div>

        <div class="form-group">
            <label for="symptoms" class="col-md-3 control-label">Include Symptoms</label>
            <div class="col-md-9">
                <v-select 
                    v-model='config.include_symptoms'
                    label="category" 
                    :maxHeight='"200px"'
                    :options="symptoms"
                    placeholder="Include Symptom"
                    multiple
                    @search="onSearchSymptom" >
                    	<template slot="option" slot-scope="option">
                            {{ option.code }} - {{ option.category }}
                        </template>
                </v-select>
            </div>
        </div>
	</div>
</template>

<script>
	import vSelect from 'vue-select';
	import axios from 'axios';
	export default {
		props: {
			config: {
				default(){
					return {}
				}
			}
        },
        
		data(){
			return {
				modelnames: [], //options for v-select modelname
                pwbs : [],
                symptoms: [],
			}
		},

		mounted(){
			// this.getConfig();
            // this.fetchIpData();
            const modelHeaderUrl = 'api/model_headers';
            this.fetchData(modelHeaderUrl, 'modelnames' );
          	const pwbUrl = 'api/pwbs';
            this.fetchData(pwbUrl, 'pwbs' );
            const symptomUrl = 'api/symptoms/all';
            this.fetchData(symptomUrl, 'symptoms');
              
        },
        
        watch : {
            'config.model_header_id' : function (newVal, oldVal){
                // console.log('wowowowowo')
                const url = 'api/pwbs';
                this.fetchData(url, 'pwbs', {model_header_id : newVal } );
            },

            'config.include_symptoms': function (newVal, oldVal){
                this.config.include_symptom_id = newVal.map(function (val){
                    return val.code;
                });
            }
        },

		methods: {
			onSearchModelname(search, loading ){
                loading(true);
                const url = 'api/model_headers';
                this.search(loading, search, this, url, 'modelnames' );
            },

            onSearchSymptom(search, loading){
            	loading(true);
            	const url = 'api/symptoms/all';
                this.search(loading, search, this, url, 'symptoms' );
            },

            onSearchPwbs(search, loading){
                loading(true);
                const url = 'api/pwbs';
                this.search(loading, search, this, url, 'pwbs');
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

			fetchData(url, column, query ){
				// const url = 'api/model_headers';
                axios.get(url, {
                    params: query
                })
                  .then(res => {
                    let response = res.data;
                    let data = response.data;
                    if (column =='modelnames') {
                    	this.modelnames = data;
                    }else if(column == 'pwbs'){
                    	this.pwbs = data;
                    }else{
                        this.symptoms = data;
                    }

                  }).catch(res => {
                    console.log(res)
                  });
            },

		},

		components : {
			vSelect
		}
	}
</script>