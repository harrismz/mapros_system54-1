<template>
	<modal>
		<div slot='header'>
			<h4>Confirmation</h4>
		</div>
		<div slot='body'>
			<p> SILAHKAN PILIH MODEL YANG SAAT INI RUNNING  </p>
		</div>
		<div slot='footer'>
			<button class="btn btn-danger" @click='configOnClick' >
				{{config_modelname }}
			</button>
			<button class="btn btn-success" @click='serverOnClick' >
				{{ server_modelname }}
			</button>

		</div>
	</modal>
</template>

<script>
	import modal from './Modal.vue';
	export default {
		props :['config_modelname', 'server_modelname' ],
		components: {
			modal
		},
		methods: {
			configOnClick(){
				console.log('configOnClick');
				this.$emit('toggleConfirm');
				this.$emit('toggleModal', 'ERROR', 'you scan wrong parts!!');
			},
			serverOnClick(){
				console.log('serverOnClick');
				this.$emit('toggleConfirm');
				this.$emit('changeConfig', this.server_modelname );
			}
		}
	}
</script>