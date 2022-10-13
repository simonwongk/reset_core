class Category extends React.Component {
	constructor( props ) {
		super( props );

		this.state = {
			curr: this.props.curr,
		};

		this.onChange = this.onChange.bind( this );
	}

	onChange( value ) {
		this.setState( {
		  curr: this.props.multi ? value.map( v => v.id ) : value.id
		} );
	}

	render() {
		var field = this.props.field;
		if ( this.props.multi ) {
			field += '[]';
		}

		var list = JSON.parse(JSON.stringify(this.props.list));
		if ( ! this.props.multi ) {
			list.unshift( { id: '-', title: '', group_title: '' } );
		}

		var value = this.props.list.filter( ({id}) => this.state.curr && ( this.props.multi ? this.state.curr.includes(id) : this.state.curr == id ) );
		return (
			<Select name={field} onChange={this.onChange} isMulti={this.props.multi} value={value} options={list} getOptionLabel={ ({id,title,group_title}) => id + ' - ' + group_title + ' - ' + title } getOptionValue={({id}) => id} />
		);
	}
}

