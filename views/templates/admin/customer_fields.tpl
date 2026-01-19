<div class="card">
    <h3 class="card-header">
        <i class="icon-user"></i> {l s='Custom Registration Fields' d='Modules.Customregistrationfields.Admin'}
    </h3>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>{l s='Account Type:' d='Modules.Customregistrationfields.Admin'}</strong> {if $customer_crf.is_professional}{l s='Professionista' d='Modules.Customregistrationfields.Admin'}{else}{l s='Privato' d='Modules.Customregistrationfields.Admin'}{/if}</p>
                {if $customer_crf.ragione_sociale}<p><strong>{l s='Ragione Sociale:' d='Modules.Customregistrationfields.Admin'}</strong> {$customer_crf.ragione_sociale}</p>{/if}
                {if $customer_crf.codice_fiscale}<p><strong>{l s='Codice Fiscale:' d='Modules.Customregistrationfields.Admin'}</strong> {$customer_crf.codice_fiscale}</p>{/if}
                {if $customer_crf.piva}<p><strong>{l s='P.IVA:' d='Modules.Customregistrationfields.Admin'}</strong> {$customer_crf.piva}</p>{/if}
                {if $customer_crf.pec}<p><strong>{l s='PEC:' d='Modules.Customregistrationfields.Admin'}</strong> {$customer_crf.pec}</p>{/if}
                {if $customer_crf.codice_destinatario}<p><strong>{l s='Codice Destinatario:' d='Modules.Customregistrationfields.Admin'}</strong> {$customer_crf.codice_destinatario}</p>{/if}
            </div>
        </div>
    </div>
</div>
