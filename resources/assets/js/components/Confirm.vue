<template>
	<modal>
		<div slot='header'>
			<h4>Confirmation</h4>
		</div>
		<div slot='body'>
			<p> SILAHKAN PILIH MODEL YANG SAAT INI RUNNING  </p>
		</div>
		<div slot='footer'>
			<div v-if="singleModel">
				<button class="btn btn-danger pull-left" @click='configOnClick' >
				{{config_modelname }}
			</button>
			<button class="btn btn-success pull-right" @click='serverOnClick' >
				{{ server_modelname }}
			</button>
			</div>

			<div class="list-group" v-if="!singleModel">
				<button class="list-group-item list-group-item-danger" @click='configOnClick' >
						{{config_modelname }}
					</button>
					<div v-for="(modelname, key) in server_modelname">
						<button class="list-group-item list-group-item-success pt-2" @click='serverOnClick(key)' >
							{{ modelname }}
						</button>
					</div>	
			</div>

			<!-- <button class="btn btn-success pull-right" @click='serverOnClick' >
				{{ server_modelname }}
			</button>
			<button class="btn btn-success pull-right" @click='serverOnClick' >
				{{ server_modelname }}
			</button> -->

		</div>
	</modal>
</template>

<script>
	import modal from './Modal.vue';
	export default {
		props :['config_modelname', 'server_modelname', 'singleModel' ],
		// data: () => {
		// 	return{
		// 		singleModel : false
		// 	}
		// },
		components: {
			modal
		},
		methods: {
			configOnClick(){
				console.log('configOnClick');
				this.$emit('toggleConfirm');
				this.$emit('toggleModal', 'ERROR', 'you scan wrong parts!!');
			},
			serverOnClick(key=null){
				console.log('serverOnClick',this.server_modelname[key]);
				this.$emit('toggleConfirm');
				this.$emit('changeConfig', this.server_modelname[key] );
			}
		}
	}
</script>

<style>
.pt-2{
	margin-top:1em !important;
}
</style>