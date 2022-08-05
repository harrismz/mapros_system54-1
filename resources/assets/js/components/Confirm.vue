<template>
	<modal>
		<div slot='header'>
			<h4>Confirmation</h4>
		</div>
		<div slot='body'>
			<p> SILAHKAN PILIH MODEL YANG SAAT INI RUNNING  </p>
		</div>
		<div slot='footer'>
			<div class="card col-6">
				<button type="button" class="btn btn-danger m-5" @click='configOnClick' >
					{{config_modelname }}
				</button>
			</div>
			<div class="card col-6" v-if="nonArray">
				<button type="button" class="btn btn-success m-5" @click='serverOnClick' >
					{{ server_modelname }}
				</button>
			</div>
			<div class="card col-6" v-if="!nonArray">
				<div v-for="modelname in server_modelname">
					<button type="button" class="btn btn-success m-5" @click='serverOnClick(modelname)' >
						{{ modelname }}
					</button>
				</div>
			</div>
		</div>
	</modal>
</template>

<script>
	import modal from './Modal.vue';
	export default {
		props :['config_modelname', 'server_modelname','nonArray' ],
		components: {
			modal
		},
		mounted: function(){
			this.arrayCheck();
		},
		methods: {
			arrayCheck(){
				this.nonArray = this.nonArray;
			},
			configOnClick(){
				console.log('configOnClick');
				this.$emit('toggleConfirm');
				this.$emit('toggleModal', 'ERROR', 'you scan wrong parts!!');
			},
			serverOnClick(modelname = null){
				console.log('serverOnClick');
				this.$emit('toggleConfirm');
				if(modelname)
				this.$emit('changeConfig', modelname );
				this.$emit('changeConfig', this.server_modelname );
			}
		}
	}
</script>

<style>
.m-5{
	margin:5px;
}
</style>