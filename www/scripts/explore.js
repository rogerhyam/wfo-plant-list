/*
    This provides the functionality for the explore tag which is mainly populating the form 
    and displaying search results.
*/


let label_map = null;
let form_data = null;
let search_results = null;

function load_label_cache(lang = 'en') {

    fetch('json_label_map.php?lang=' + lang)
        .then((response) => response.json())
        .then((json) => {
            // console.log(json);
            label_map = json;
        });

}

function update_search_results() {

    const params = new URLSearchParams(document.cookie.substring(7));
    fetch('json_search_results.php?' + params)
        .then((response) => response.json())
        .then((json) => {
            console.log(json);
            search_results = json;
        });

}

function search_param_change(input) {

    //console.log(label_map);

    var form_data = new FormData(input.form);

    // save the form state
    let params = new URLSearchParams(form_data);
    document.cookie = 'search=' + params.toString();

    // pass all form parameters to the page
    fetch('json_search_results.php?' + params)
        .then((response) => response.json())
        .then((json) => {

            console.log(json);

            // populate form
            const results_wrapper = document.getElementById('search_results');
            const facets_wrapper = document.getElementById('facet_inputs');

            // fill in search results
            if (json.response && json.response.docs && json.response.docs.length > 0) {

                let search_terms = json.getParams.search;
                const re = new RegExp(search_terms, "g");

                results_wrapper.innerHTML = `<ul style="padding-left: 0px;">
                ${json.response.docs.map(doc => {

                    // remove the first one as it is the same as the accepted
                    doc.all_names_alpha_ss.shift();

                    let syns = "";
                    if (doc.all_names_alpha_ss.length > 0) {
                        syns = `
                         <div style="margin-top: 0.3em;">
                        <strong>Synonyms: </strong>
                        ${doc.all_names_alpha_ss.map(syn => {
                            // we need to highlight the search terms if they are found in the  synonyms
                            highlighted = syn.replace(re, `<strong class="syn_highlight">${search_terms}</strong>`);
                            return highlighted;
                        }).join('; ')};
                        </div>
                        `;
                    }

                    return `<li style="list-style-type: none;margin-bottom: 0.6em;">
                        <a href="explore_taxon.php?id=${doc.id}">${doc.full_name_string_html_s}</a>
                        ${syns}
                        <div style="margin-top: 0.3em;">
                        <strong>Path:</strong>${doc.name_path_s}
                        </div>
                    </li>`
                }).join('')
                    }
                </ul > `;
            } else {
                // no docs returned
                results_wrapper.innerHTML = "<p>No taxa were found matching your query.</p>"
            }// got some docs

            // now populate the facets check boxes
            let facets_html = [];
            console.log(json.getParams);
            const formatter = new Intl.NumberFormat('en'); // FIXME pull this from session

            for (const [facet_name, facet] of Object.entries(json.facets)) {
                if (facet_name == 'count') continue; // ignore the total count

                const facet_checkboxes = facet.buckets.map(fv => {

                    const value_name = `${facet_name}_${fv.val}`;
                    return `<li style="list-style-type: none;">
                        <input 
                        type="checkbox" 
                        name="${value_name}"
                        value="checked"
                        onchange="search_param_change(this)" 
                        ${json.getParams.hasOwnProperty(value_name) ? 'checked' : ''}
                         />
                        ${label_map[fv.val].label} (${formatter.format(fv.count)})
                        </li>`;
                });

                const facet_html = `
                    <h3>${label_map[facet_name].label}</h3>
                    <ul>
                    ${facet_checkboxes.join('')}
                    </ul>
                `;

                facets_html.push(facet_html);
            }


            facets_wrapper.innerHTML = facets_html.join('');


        });

}

function initialize_form() {
    const form = document.getElementById("main_form");
    const params = new URLSearchParams(document.cookie.substring(7));
    const entries = params.entries();

    for (const [key, val] of entries) {
        console.log(`${key} - ${val}`);
        //http://javascript-coder.com/javascript-form/javascript-form-value.phtml
        const input = form.elements[key];
        switch (input.type) {
            case 'checkbox': input.checked = !!val; break;
            default: input.value = val; break;
        }
    }

}
