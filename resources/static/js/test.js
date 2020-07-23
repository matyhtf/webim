// function Person() {
//
// }
// Person.prototype.name = 'Kevin';
// Person.name1 = 'Kevin1';
// var person = new Person();
// person.name = 'Daisy';
//
// console.log(Person.__proto__)
// console.log(person.__proto__.name)
// console.log(Person.prototype.__proto__.__proto__)
// console.log(person.name);
// delete person.name;
// console.log(person.name);
// console.log(Person === Person.prototype.constructor);
// console.log(person.__proto__.constructor == Person);
function DOMEval( code, doc, node ) {
    doc = doc || document;

    var i,
        script = doc.createElement( "script" );

    script.text = code;
    if ( node ) {
        for ( i in preservedScriptAttributes ) {
            if ( node[ i ] ) {
                script[ i ] = node[ i ];
            }
        }
    }
    doc.head.appendChild( script ).parentNode.removeChild( script );
}
DOMEval('console.log("dd")');